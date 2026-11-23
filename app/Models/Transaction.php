<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

    public const IN = 'pemasukan';
    public const OUT = 'pengeluaran';
    public const MOVE = 'pindah';
    public const TYPES = [self::IN, self::OUT, self::MOVE];

    protected $fillable = [
        'date', 'type', 'account_id', 'target_account_id', 'category_id',
        'title', 'quantity', 'unit', 'amount', 'descriptions', 'user_id',
        'source', 'telegram_message_id', 'line_no', 'is_locked', 'import_batch',
    ];

    protected $casts = [
        'date'      => 'date',
        'quantity'  => 'decimal:2',
        'amount'    => 'integer',
        'is_locked' => 'boolean',
    ];

    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function targetAccount(): BelongsTo { return $this->belongsTo(Account::class, 'target_account_id'); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function telegramMessage(): BelongsTo { return $this->belongsTo(TelegramMessage::class); }

    /** Label tampilan: nama barang/toko (dari chat/excel) atau keterangan. */
    public function getLabelAttribute(): string
    {
        return $this->title ?: ($this->descriptions ?: '-');
    }
}
