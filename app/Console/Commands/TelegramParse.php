<?php

namespace App\Console\Commands;

use App\Models\TelegramMessage;
use App\Services\Telegram\MessageProcessor;
use Illuminate\Console\Command;

class TelegramParse extends Command
{
    protected $signature = 'telegram:parse {--failed : ikut parse ulang yang gagal} {--all : parse ulang SEMUA (kecuali ignored)}';
    protected $description = 'Parse chat mentah Telegram menjadi pengeluaran/pemasukan';

    public function handle(MessageProcessor $processor): int
    {
        $q = TelegramMessage::query()->where('parse_status', '!=', 'ignored')->orderBy('message_date');

        if (!$this->option('all')) {
            $statuses = $this->option('failed') ? ['pending', 'failed'] : ['pending'];
            $q->whereIn('parse_status', $statuses);
        }

        $n = 0;
        $q->chunkById(200, function ($chunk) use ($processor, &$n) {
            foreach ($chunk as $m) {
                $processor->process($m);
                $n++;
            }
        });

        $this->info("{$n} pesan diproses.");
        return self::SUCCESS;
    }
}
