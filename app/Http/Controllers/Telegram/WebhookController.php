<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Jobs\ParseTelegramMessage;
use App\Services\Telegram\MessageStore;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __invoke(Request $request, MessageStore $store)
    {
        $secret = config('telegram.webhook_secret');
        if ($secret && !hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            abort(403);
        }

        $update = $request->all();
        $edited = isset($update['edited_message']);
        $msg = $update['message'] ?? $update['edited_message'] ?? null;

        if (!$msg || !isset($msg['chat']['id'], $msg['message_id']) || !(isset($msg['text']) || isset($msg['caption']))) {
            return response()->json(['ok' => true]);
        }

        $allowed = config('telegram.allowed_chat_ids');
        if ($allowed && !in_array((string) $msg['chat']['id'], $allowed, true)) {
            return response()->json(['ok' => true]);
        }

        // 1) simpan mentah
        $saved = $store->upsert($msg, $update['update_id'] ?? null, $edited);

        // 2) (opsional) konversi di background
        if (config('telegram.auto_parse') && $saved->parse_status === 'pending') {
            ParseTelegramMessage::dispatch($saved->id);
        }

        return response()->json(['ok' => true]);
    }
}
