<?php

namespace App\Services\Telegram;

use App\Models\TelegramMessage;
use App\Support\AmountParser;

/**
 * Format baris:  <Nama Barang/Toko> [(qty) [satuan]] <total, mis. 120 K>
 * Contoh: "Lele (2) Kg 46 K", "Indomaret 96,1 K", "Biaya Admin 3,5 K", "Mama 400k"
 *
 * Tipe (pengeluaran/pemasukan):
 *  - baris diawali "+"           => pemasukan
 *  - baris pertama "#masuk"      => semua baris pemasukan ("#keluar" => pengeluaran)
 *  - pengirim ada di TELEGRAM_INCOME_SENDER_IDS => pemasukan
 *  - selain itu                  => pengeluaran
 */
class MessageParser
{
    private const TAGS = [
        '#masuk' => 'pemasukan', '#income' => 'pemasukan', '#pemasukan' => 'pemasukan',
        '#keluar' => 'pengeluaran', '#expense' => 'pengeluaran', '#pengeluaran' => 'pengeluaran',
    ];

    private const UNITS = 'kg|g|gr|gram|ons|l|ltr|liter|ml|pcs|pc|biji|buah|bungkus|bks|porsi|pack|box|botol|kaleng|lembar|unit|ikat|sisir';

    /** @return array{rows: array<int,array>, skipped: array<int,array>} */
    public function parseMessage(TelegramMessage $m): array
    {
        $lines = preg_split('/\R/u', trim((string) $m->text));
        $default = $this->defaultType($m, $lines);

        $rows = [];
        $skipped = [];
        foreach ($lines as $i => $raw) {
            $line = trim($raw);
            if ($line === '' || isset(self::TAGS[mb_strtolower($line)])) {
                continue;
            }
            $parsed = $this->parseLine($line, $default);
            if ($parsed) {
                $rows[] = $parsed + ['line_no' => $i + 1];
            } else {
                $skipped[] = ['line_no' => $i + 1, 'text' => $line];
            }
        }

        return compact('rows', 'skipped');
    }

    public function parseLine(string $line, string $type = 'pengeluaran'): ?array
    {
        if (preg_match('/^\+\s*(.+)$/u', $line, $m)) {
            $type = 'pemasukan';
            $line = $m[1];
        } elseif (preg_match('/^-\s+(.+)$/u', $line, $m)) {
            $type = 'pengeluaran';
            $line = $m[1];
        }

        $re = '/^(?<head>.*?)\s+(?:rp\.?\s*)?(?<num>\d[\d.,]*)\s*(?<suffix>k|rb|ribu|jt|juta)?$/iu';
        if (!preg_match($re, $line, $m)) {
            return null;
        }

        $suffix = $m['suffix'] ?? '';
        $amount = AmountParser::toRupiah($m['num'], $suffix ?: null);
        if (!$amount || ($suffix === '' && $amount < config('telegram.min_plain_amount', 500))) {
            return null;
        }

        $head = $m['head'];
        $qty = null;
        $unit = null;

        if (preg_match('/\((\d+(?:[.,]\d+)?)\)\s*(?:\b(' . self::UNITS . ')\b)?/iu', $head, $q)) {
            $qty = (float) str_replace(',', '.', $q[1]);
            $unit = isset($q[2]) && $q[2] !== '' ? strtolower($q[2]) : null;
            $head = str_replace($q[0], ' ', $head);
        } elseif (preg_match('/(?<!\w)(?:x\s?(\d+(?:[.,]\d+)?)|(\d+(?:[.,]\d+)?)\s?x)(?!\w)/iu', $head, $q)) {
            $qty = (float) str_replace(',', '.', ($q[1] ?? '') !== '' ? $q[1] : $q[2]);
            $head = str_replace($q[0], ' ', $head);
        }

        $title = trim(preg_replace('/\s+/u', ' ', $head), " \t-:,.");
        if ($title === '' || !preg_match('/\p{L}/u', $title)) {
            return null;
        }

        return [
            'type'     => $type,
            'title'    => $title,
            'quantity' => $qty,
            'unit'     => $unit,
            'amount'   => $amount,
        ];
    }

    private function defaultType(TelegramMessage $m, array $lines): string
    {
        $first = mb_strtolower(trim($lines[0] ?? ''));
        if (isset(self::TAGS[$first])) {
            return self::TAGS[$first];
        }
        if ($m->sender_id && in_array((string) $m->sender_id, config('telegram.income_sender_ids', []), true)) {
            return 'pemasukan';
        }
        return 'pengeluaran';
    }
}
