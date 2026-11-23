<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramMessage extends Model
{
    protected $fillable = [
        'chat_id', 'telegram_message_id', 'update_id', 'chat_title', 'sender_id', 'sender_name',
        'text', 'message_date', 'edited_at', 'parse_status', 'parsed_at', 'parse_note', 'payload',
    ];

    protected $casts = [
        'message_date' => 'datetime',
        'edited_at'    => 'datetime',
        'parsed_at'    => 'datetime',
        'payload'      => 'array',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
