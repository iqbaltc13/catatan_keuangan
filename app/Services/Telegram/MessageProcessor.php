<?php

namespace App\Services\Telegram;

use App\Models\Account;
use App\Models\TelegramMessage;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/** Mengubah chat mentah -> baris tabel transactions. Aman dijalankan ulang (idempotent). */
class MessageProcessor
{
    public function __construct(private MessageParser $parser) {}

    public function process(TelegramMessage $m): array
    {
        if (trim((string) $m->text) === '') {
            $m->update(['parse_status' => 'ignored', 'parsed_at' => now()]);
            return ['rows' => [], 'skipped' => []];
        }

        $result = $this->parser->parseMessage($m);
        $date = $m->message_date->copy()->timezone(config('app.timezone'))->toDateString();
        $accountId = ($name = config('telegram.default_account'))
            ? Account::where('name', $name)->value('id')
            : null;

        DB::transaction(function () use ($m, $result, $date, $accountId) {
            // baris yg sudah diedit manual / sengaja dihapus tidak ditimpa
            $locked = [];
            Transaction::withTrashed()->where('telegram_message_id', $m->id)->get()
                ->each(function ($t) use (&$locked) {
                    if ($t->is_locked || $t->trashed()) {
                        $locked[$t->line_no] = true;
                    } else {
                        $t->forceDelete();
                    }
                });

            foreach ($result['rows'] as $row) {
                if (isset($locked[$row['line_no']])) {
                    continue;
                }
                Transaction::create([
                    'date'                => $date,
                    'type'                => $row['type'],
                    'account_id'          => $accountId,
                    'title'               => $row['title'],
                    'quantity'            => $row['quantity'],
                    'unit'                => $row['unit'],
                    'amount'              => $row['amount'],
                    'source'              => 'telegram',
                    'telegram_message_id' => $m->id,
                    'line_no'             => $row['line_no'],
                ]);
            }

            $note = $result['skipped']
                ? 'Baris tidak terbaca: ' . collect($result['skipped'])->pluck('line_no')->implode(', ')
                : null;

            $m->update([
                'parse_status' => $result['rows'] ? 'parsed' : 'failed',
                'parsed_at'    => now(),
                'parse_note'   => $note,
            ]);
        });

        return $result;
    }
}
