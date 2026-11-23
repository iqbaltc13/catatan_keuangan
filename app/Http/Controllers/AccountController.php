<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use App\Support\AmountParser;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function index()
    {
        return view('account.index', ['accounts' => Account::withBalances()->get()]);
    }

    public function create()
    {
        return view('account.form', ['account' => null]);
    }

    public function store(Request $request)
    {
        Account::create($this->validated($request));
        return redirect()->route('account')->with('success', 'Akun berhasil ditambahkan!');
    }

    public function edit(string $id)
    {
        return view('account.form', ['account' => Account::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $account = Account::findOrFail($id);
        $account->update($this->validated($request, $account->id));
        return redirect()->route('account')->with('success', 'Akun berhasil diubah');
    }

    public function destroy(string $id)
    {
        $used = Transaction::where('account_id', $id)->orWhere('target_account_id', $id)->exists();
        if ($used) {
            return back()->withErrors(['account' => 'Akun sudah dipakai transaksi, tidak bisa dihapus.']);
        }
        Account::findOrFail($id)->delete();
        return redirect()->route('account')->with('success', 'Akun berhasil dihapus!');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $request->merge(['opening_balance' => $request->filled('opening_balance')
            ? AmountParser::parse(str_replace('-', '', $request->input('opening_balance'))) * ($this->isNegative($request->input('opening_balance')) ? -1 : 1)
            : 0]);

        return $request->validate([
            'name'            => ['required', 'string', 'max:255', Rule::unique('accounts', 'name')->ignore($ignoreId)],
            'descriptions'    => ['nullable', 'string'],
            'opening_balance' => ['required', 'integer'],
        ]);
    }

    private function isNegative(mixed $v): bool
    {
        return str_starts_with(trim((string) $v), '-');
    }
}
