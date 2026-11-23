<?php

namespace App\Services\Telegram;

use App\Models\TelegramMessage;
use Illuminate\Support\Carbon;

/** Menyimpan chat Telegram sebagai data mentah (tanpa konversi ke pengeluaran/pemasukan). */
class MessageStore
{
    public function upsert(array $msg, ?int $updateId = null, bool $edited = false): TelegramMessage
    {
        $from = $msg['from'] ?? [];
        $name = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')) ?: ($from['username'] ?? null);
        $text = $msg['text'] ?? $msg['caption'] ?? null;

        $model = TelegramMessage::firstOrNew([
            'chat_id'             => $msg['chat']['id'],
            'telegram_message_id' => $msg['message_id'],
        ]);

        $changed = $model->exists && $model->text !== $text;

        $model->fill([
            'update_id'    => $updateId,
            'chat_title'   => $msg['chat']['title'] ?? ($msg['chat']['first_name'] ?? null),
            'sender_id'    => $from['id'] ?? null,
            'sender_name'  => $name,
            'text'         => $text,
            'message_date' => $model->message_date ?? Carbon::createFromTimestamp($msg['date'] ?? time()),
            'payload'      => $msg,
        ]);

        if ($edited) {
            $model->edited_at = Carbon::createFromTimestamp($msg['edit_date'] ?? time());
        }
        if (!$model->exists || $changed) {
            $model->parse_status = 'pending';
        }

        $model->save();
        return $model;
    }
}
