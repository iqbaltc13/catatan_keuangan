<?php

namespace App\Console\Commands;

use App\Models\TelegramMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/** Backfill riwayat chat lama dari Telegram Desktop > Export chat history > JSON. */
class TelegramImportExport extends Command
{
    protected $signature = 'telegram:import-export {file} {--chat-id= : chat_id asli grup, mis. -1001234567890 (WAJIB utk grup agar tidak duplikat dgn webhook)}';
    protected $description = 'Import result.json hasil export Telegram Desktop sebagai chat mentah';

    public function handle(): int
    {
        $data = json_decode(file_get_contents($this->argument('file')), true);
        if (!$data || !isset($data['messages'])) {
            $this->error('File tidak valid.');
            return self::FAILURE;
        }

        $chatId = (int) ($this->option('chat-id') ?: $data['id']);
        $n = 0;

        foreach ($data['messages'] as $m) {
            if (($m['type'] ?? '') !== 'message') {
                continue;
            }
            $text = is_array($m['text']) ? collect($m['text'])->map(fn ($p) => is_array($p) ? ($p['text'] ?? '') : $p)->implode('') : $m['text'];
            if (trim((string) $text) === '') {
                continue;
            }

            $date = isset($m['date_unixtime'])
                ? Carbon::createFromTimestamp((int) $m['date_unixtime'])
                : Carbon::parse($m['date'], config('app.timezone'));

            TelegramMessage::updateOrCreate(
                ['chat_id' => $chatId, 'telegram_message_id' => $m['id']],
                [
                    'chat_title'   => $data['name'] ?? null,
                    'sender_id'    => isset($m['from_id']) ? (int) preg_replace('/\D/', '', $m['from_id']) : null,
                    'sender_name'  => $m['from'] ?? null,
                    'text'         => $text,
                    'message_date' => $date,
                    'edited_at'    => isset($m['edited_unixtime']) ? Carbon::createFromTimestamp((int) $m['edited_unixtime']) : null,
                    'parse_status' => 'pending',
                    'payload'      => $m,
                ]
            );
            $n++;
        }

        $this->info("{$n} pesan diimpor (status: pending). Jalankan: php artisan telegram:parse");
        return self::SUCCESS;
    }
}
