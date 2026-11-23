<?php

namespace App\Imports;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\AmountParser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Header (baris 1):
 *   tanggal | tipe | akun | akun_tujuan | kategori | nama | qty | satuan | jumlah | keterangan
 * Alias yg juga dikenali (format prototype lama): date, type, account_id, target_account_id, category_id, amount, descriptions
 * tipe: pemasukan | pengeluaran | pindah  (juga: masuk/keluar/transfer, atau angka 1/2/3). Kosong = pengeluaran.
 */
class TransactionsImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;
    public array $errors = [];
    public string $batch;

    private array $accounts;     // lower(name) => id
    private array $accountIds;   // id => true

    private const TYPE_MAP = [
        'pemasukan' => 'pemasukan', 'masuk' => 'pemasukan', 'income' => 'pemasukan', '1' => 'pemasukan',
        'pengeluaran' => 'pengeluaran', 'keluar' => 'pengeluaran', 'expense' => 'pengeluaran', '2' => 'pengeluaran',
        'pindah' => 'pindah', 'transfer' => 'pindah', '3' => 'pindah',
    ];

    public function __construct()
    {
        $this->batch = (string) Str::uuid();
        $all = Account::pluck('id', 'name');
        $this->accounts = $all->mapWithKeys(fn ($id, $n) => [mb_strtolower($n) => $id])->all();
        $this->accountIds = $all->mapWithKeys(fn ($id) => [$id => true])->all();
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $line = $i + 2;
            if ($row->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
                continue;
            }

            $date = $this->date($this->pick($row, 'tanggal', 'date'));
            $amount = AmountParser::parse($this->pick($row, 'jumlah', 'amount'));
            $type = self::TYPE_MAP[mb_strtolower(trim((string) ($this->pick($row, 'tipe', 'type') ?? 'pengeluaran')))] ?? null;
            $title = trim((string) $this->pick($row, 'nama', 'title'));
            $desc = trim((string) $this->pick($row, 'keterangan', 'descriptions'));

            if (!$date || !$amount || !$type) {
                $this->errors[] = "Baris {$line}: tanggal / tipe / jumlah tidak valid.";
                continue;
            }
            if ($title === '' && $desc === '') {
                $this->errors[] = "Baris {$line}: isi minimal kolom nama atau keterangan.";
                continue;
            }

            $accountId = $this->account($this->pick($row, 'akun', 'account_id', 'account'));
            if ($accountId === false) {
                $this->errors[] = "Baris {$line}: akun tidak ditemukan.";
                continue;
            }
            $targetId = null;
            if ($type === 'pindah') {
                $targetId = $this->account($this->pick($row, 'akun_tujuan', 'target_account_id'));
                if (!$targetId || !$accountId || $targetId === $accountId) {
                    $this->errors[] = "Baris {$line}: pindah saldo butuh akun asal dan akun tujuan yang berbeda.";
                    continue;
                }
            }

            $qty = $this->pick($row, 'qty', 'quantity');

            Transaction::create([
                'date'              => $date,
                'type'              => $type,
                'account_id'        => $accountId ?: null,
                'target_account_id' => $targetId,
                'category_id'       => $this->category($this->pick($row, 'kategori', 'category_id', 'category'), $type),
                'title'             => $title ?: null,
                'quantity'          => is_numeric($qty) ? $qty : null,
                'unit'              => $this->pick($row, 'satuan', 'unit') ?: null,
                'amount'            => $amount,
                'descriptions'      => $desc ?: null,
                'user_id'           => auth()->id(),
                'source'            => 'excel',
                'import_batch'      => $this->batch,
            ]);
            $this->imported++;
        }
    }

    private function pick(Collection $row, string ...$keys): mixed
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && $row[$k] !== '') {
                return $row[$k];
            }
        }
        return null;
    }

    /** null = kosong (tanpa akun), false = diisi tapi tidak ada, int = id akun */
    private function account(mixed $v): int|false|null
    {
        if ($v === null || $v === '') {
            $default = config('telegram.default_account');
            return $default ? ($this->accounts[mb_strtolower($default)] ?? null) : null;
        }
        if (is_numeric($v) && isset($this->accountIds[(int) $v])) {
            return (int) $v;
        }
        return $this->accounts[mb_strtolower(trim((string) $v))] ?? false;
    }

    private function category(mixed $v, string $type): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return Category::whereKey((int) $v)->value('id');
        }
        return Category::firstOrCreate(['name' => trim((string) $v), 'type' => $type])->id;
    }

    private function date(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        try {
            if (is_numeric($v)) {
                return Carbon::instance(Date::excelToDateTimeObject($v))->toDateString();
            }
            foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $fmt) {
                try {
                    return Carbon::createFromFormat($fmt, trim($v))->toDateString();
                } catch (\Throwable) {
                }
            }
            return Carbon::parse($v)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
