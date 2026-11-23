<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\TelegramMessage;
use App\Models\Transaction;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $accounts = Account::withBalances()->get();
        $monthRange = [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];

        return view('dashboard', [
            'totalBalance'      => $accounts->sum('balance'),
            'monthIncome'       => Transaction::where('type', Transaction::IN)->whereBetween('date', $monthRange)->sum('amount'),
            'monthExpense'      => Transaction::where('type', Transaction::OUT)->whereBetween('date', $monthRange)->sum('amount'),
            'pendingChats'      => TelegramMessage::whereIn('parse_status', ['pending', 'failed'])->count(),
            'chartData'         => [
                'labels' => $accounts->pluck('name'),
                'data'   => $accounts->pluck('balance')->map(fn ($v) => (int) $v),
                'ids'    => $accounts->pluck('id'),
            ],
            'chartIncomeData'   => $this->byCategory(Transaction::IN),
            'chartExpenseData'  => $this->byCategory(Transaction::OUT),
            'totalIncome'       => Transaction::where('type', Transaction::IN)->sum('amount'),
            'totalExpense'      => Transaction::where('type', Transaction::OUT)->sum('amount'),
        ]);
    }

    private function byCategory(string $type): array
    {
        $rows = Transaction::query()
            ->where('transactions.type', $type)
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->selectRaw("categories.id AS category_id, COALESCE(categories.name, 'Tanpa kategori') AS name, SUM(transactions.amount) AS total")
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total')
            ->get();

        return [
            'labels' => $rows->pluck('name'),
            'data'   => $rows->pluck('total')->map(fn ($v) => (int) $v),
            'ids'    => $rows->pluck('category_id'),
        ];
    }
}
