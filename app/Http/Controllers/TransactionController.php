<?php

namespace App\Http\Controllers;

use App\Exports\TemplateExport;
use App\Imports\TransactionsImport;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\AmountParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $query = Transaction::query()
            ->with(['account', 'targetAccount', 'category', 'telegramMessage:id,text,sender_name'])
            ->when($request->from, fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->account_id, fn ($q, $v) => $q->where(fn ($w) => $w->where('account_id', $v)->orWhere('target_account_id', $v)))
            ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->source, fn ($q, $v) => $q->where('source', $v))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('title', 'ilike', "%{$s}%")->orWhere('descriptions', 'ilike', "%{$s}%")));

        return view('transaksi.index', [
            'transactions' => (clone $query)->orderByDesc('date')->orderByDesc('id')->paginate(30)->withQueryString(),
            'totalIncome'  => (clone $query)->where('type', Transaction::IN)->sum('amount'),
            'totalExpense' => (clone $query)->where('type', Transaction::OUT)->sum('amount'),
            'filters'      => $request->only('from', 'to', 'type', 'account_id', 'category_id', 'source', 'search'),
            'accounts'     => Account::orderBy('name')->get(['id', 'name']),
            'categories'   => Category::orderBy('type')->orderBy('name')->get(['id', 'name', 'type']),
        ]);
    }

    public function create(): View
    {
        return view('transaksi.form', $this->formData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        Transaction::create($this->validated($request) + ['user_id' => auth()->id(), 'source' => 'manual']);
        return redirect()->route('transaksi')->with('success', 'Transaksi berhasil disimpan!');
    }

    public function edit(string $id): View
    {
        return view('transaksi.form', $this->formData(Transaction::findOrFail($id)));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $transaction = Transaction::findOrFail($id);
        $data = $this->validated($request);
        // hasil Telegram yang diedit manual tidak akan ditimpa saat parse ulang
        $transaction->update($data + ['is_locked' => $transaction->source === 'telegram' ? true : $transaction->is_locked]);

        return redirect()->route('transaksi')->with('success', 'Transaksi berhasil diperbarui');
    }

    public function destroy(string $id): RedirectResponse
    {
        Transaction::findOrFail($id)->delete(); // soft delete; saldo akun dihitung dari transaksi, tidak perlu disesuaikan
        return back()->with('success', 'Transaksi berhasil dihapus!');
    }

    // ---------- Import Excel ----------
    public function import(): View
    {
        return view('transaksi.import');
    }

    public function importStore(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120']]);

        $import = new TransactionsImport();
        Excel::import($import, $request->file('file'));

        return redirect()->route('transaksi')
            ->with('success', "{$import->imported} baris berhasil diimpor.")
            ->with('import_errors', $import->errors);
    }

    public function template()
    {
        return Excel::download(new TemplateExport(), 'template-transaksi.xlsx');
    }

    // ---------- helpers ----------
    private function formData(?Transaction $transaction): array
    {
        return [
            'transaction' => $transaction,
            'accounts'    => Account::orderBy('name')->get(['id', 'name']),
            'categories'  => Category::orderBy('name')->get(['id', 'name', 'type']),
        ];
    }

    private function validated(Request $request): array
    {
        // nominal boleh ditulis "120 K", "1,5jt", "50.000"
        $request->merge(['amount' => AmountParser::parse($request->input('amount'))]);

        $data = $request->validate([
            'date'              => ['required', 'date'],
            'type'              => ['required', Rule::in(Transaction::TYPES)],
            'account_id'        => ['required', 'exists:accounts,id'],
            'target_account_id' => ['nullable', 'required_if:type,pindah', 'exists:accounts,id', 'different:account_id'],
            'category_id'       => ['nullable', 'exists:categories,id'],
            'title'             => ['nullable', 'string', 'max:255'],
            'quantity'          => ['nullable', 'numeric', 'min:0'],
            'unit'              => ['nullable', 'string', 'max:20'],
            'amount'            => ['required', 'integer', 'min:1'],
            'descriptions'      => ['nullable', 'string'],
        ], ['amount.required' => 'Jumlah tidak valid (contoh: 120000 atau 120 K).']);

        if ($data['type'] !== Transaction::MOVE) {
            $data['target_account_id'] = null;
        }
        return $data;
    }
}
