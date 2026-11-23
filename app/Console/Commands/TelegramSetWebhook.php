<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook {base_url : mis. https://domainanda.com}';
    protected $description = 'Daftarkan webhook bot Telegram';

    public function handle(): int
    {
        $token = config('telegram.bot_token');
        $res = Http::post("https://api.telegram.org/bot{$token}/setWebhook", [
            'url'             => rtrim($this->argument('base_url'), '/') . '/api/telegram/webhook',
            'secret_token'    => config('telegram.webhook_secret'),
            'allowed_updates' => ['message', 'edited_message'],
        ]);
        $this->line($res->body());
        return $res->successful() ? self::SUCCESS : self::FAILURE;
    }
}
