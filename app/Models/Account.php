<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = ['name', 'descriptions', 'opening_balance'];

    protected $casts = ['opening_balance' => 'integer'];

    /** Saldo berjalan semua akun: saldo awal + pemasukan - pengeluaran - pindah keluar + pindah masuk. */
    public static function withBalances()
    {
        return static::query()->selectRaw("
            accounts.*,
            accounts.opening_balance
            + COALESCE((SELECT SUM(CASE t.type WHEN 'pemasukan' THEN t.amount ELSE -t.amount END)
                        FROM transactions t
                        WHERE t.account_id = accounts.id AND t.deleted_at IS NULL), 0)
            + COALESCE((SELECT SUM(t.amount) FROM transactions t
                        WHERE t.target_account_id = accounts.id AND t.type = 'pindah' AND t.deleted_at IS NULL), 0)
            AS balance
        ")->orderBy('name');
    }
}
