<?php

namespace App\Jobs;

use App\Models\TelegramMessage;
use App\Services\Telegram\MessageProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ParseTelegramMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $messageId) {}

    public function handle(MessageProcessor $processor): void
    {
        if ($m = TelegramMessage::find($this->messageId)) {
            if ($m->parse_status !== 'ignored') {
                $processor->process($m);
            }
        }
    }
}
