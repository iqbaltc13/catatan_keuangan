<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** Laporan harian (per bulan), bulanan (per tahun), tahunan. Semua query kompatibel PostgreSQL. */
class ReportController extends Controller
{
    public function harian(Request $request): View
    {
        $month = Carbon::createFromFormat('Y-m', $request->input('month', now()->format('Y-m')))->startOfMonth();
        $range = [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()];

        $rows = $this->buckets('to_char(transactions."date", \'YYYY-MM-DD\')', $range)->map(fn ($b) => [
            'label'   => Carbon::parse($b->bucket)->translatedFormat('l, d F Y'),
            'short'   => Carbon::parse($b->bucket)->format('d M'),
            'income'  => (int) $b->income,
            'expense' => (int) $b->expense,
            'link'    => route('transaksi', ['from' => $b->bucket, 'to' => $b->bucket]),
        ]);

        return $this->render('Laporan Harian - ' . $month->translatedFormat('F Y'), 'laporan.harian', $rows, $range,
            ['type' => 'month', 'name' => 'month', 'value' => $month->format('Y-m')], 'Per hari');
    }

    public function bulanan(Request $request): View
    {
        $year = (int) $request->input('year', now()->year);
        $range = ["{$year}-01-01", "{$year}-12-31"];

        $rows = $this->buckets('EXTRACT(MONTH FROM transactions."date")::int', $range)->map(function ($b) use ($year) {
            $start = Carbon::create($year, (int) $b->bucket, 1);
            return [
                'label'   => $start->translatedFormat('F Y'),
                'short'   => $start->translatedFormat('M'),
                'income'  => (int) $b->income,
                'expense' => (int) $b->expense,
                'link'    => route('transaksi', ['from' => $start->toDateString(), 'to' => $start->copy()->endOfMonth()->toDateString()]),
            ];
        });

        return $this->render("Laporan Bulanan - {$year}", 'laporan.bulanan', $rows, $range,
            ['type' => 'number', 'name' => 'year', 'value' => $year], 'Per bulan');
    }

    public function tahunan(): View
    {
        $range = ['1900-01-01', '2999-12-31'];

        $rows = $this->buckets('EXTRACT(YEAR FROM transactions."date")::int', $range)->map(fn ($b) => [
            'label'   => (string) $b->bucket,
            'short'   => (string) $b->bucket,
            'income'  => (int) $b->income,
            'expense' => (int) $b->expense,
            'link'    => route('transaksi', ['from' => "{$b->bucket}-01-01", 'to' => "{$b->bucket}-12-31"]),
        ]);

        return $this->render('Laporan Tahunan', 'laporan.tahunan', $rows, $range, null, 'Per tahun');
    }

    private function render(string $title, string $route, Collection $rows, array $range, ?array $picker, string $unit): View
    {
        return view('laporan.index', [
            'title'   => $title,
            'route'   => $route,
            'picker'  => $picker,
            'unit'    => $unit,
            'rows'    => $rows,
            'income'  => $rows->sum('income'),
            'expense' => $rows->sum('expense'),
            'range'   => $range,
            'incomeByCategory'  => $this->byCategory(Transaction::IN, $range),
            'expenseByCategory' => $this->byCategory(Transaction::OUT, $range),
        ]);
    }

    private function buckets(string $expr, array $range): Collection
    {
        return Transaction::query()
            ->whereBetween('transactions.date', $range)
            ->whereIn('transactions.type', [Transaction::IN, Transaction::OUT])
            ->selectRaw("{$expr} AS bucket,
                SUM(CASE WHEN transactions.type = 'pemasukan' THEN transactions.amount ELSE 0 END) AS income,
                SUM(CASE WHEN transactions.type = 'pengeluaran' THEN transactions.amount ELSE 0 END) AS expense")
            ->groupByRaw('1')
            ->orderByRaw('1')
            ->get();
    }

    private function byCategory(string $type, array $range): array
    {
        $rows = Transaction::query()
            ->whereBetween('transactions.date', $range)
            ->where('transactions.type', $type)
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->selectRaw("categories.id AS category_id, COALESCE(categories.name, 'Tanpa kategori') AS name, SUM(transactions.amount) AS total")
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total')
            ->get();

        return [
            'labels' => $rows->pluck('name'),
            'data'   => $rows->pluck('total')->map(fn ($v) => (int) $v),
            'links'  => $rows->map(fn ($r) => $r->category_id
                ? route('transaksi', ['category_id' => $r->category_id, 'from' => $range[0] > '1900-01-01' ? $range[0] : null, 'to' => $range[0] > '1900-01-01' ? $range[1] : null])
                : null),
        ];
    }
}
