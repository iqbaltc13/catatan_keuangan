<?php

namespace App\Http\Controllers;

use App\Models\TelegramMessage;
use App\Services\Telegram\MessageParser;
use App\Services\Telegram\MessageProcessor;
use Illuminate\Http\Request;

class TelegramMessageController extends Controller
{
    public function index(Request $request)
    {
        $messages = TelegramMessage::query()
            ->withCount('transactions')
            ->when($request->status, fn ($q, $s) => $q->where('parse_status', $s))
            ->when($request->search, fn ($q, $s) => $q->where('text', 'ilike', "%{$s}%"))
            ->when($request->from, fn ($q, $d) => $q->whereDate('message_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('message_date', '<=', $d))
            ->orderByDesc('message_date')
            ->paginate(30)
            ->withQueryString();

        return view('telegram.index', [
            'messages' => $messages,
            'filters'  => $request->only('status', 'search', 'from', 'to'),
            'counts'   => TelegramMessage::selectRaw('parse_status, count(*) c')->groupBy('parse_status')->pluck('c', 'parse_status'),
        ]);
    }

    /** Pratinjau hasil parse tanpa menyimpan (JSON untuk modal). */
    public function preview(TelegramMessage $message, MessageParser $parser)
    {
        return response()->json($parser->parseMessage($message));
    }

    public function parse(TelegramMessage $message, MessageProcessor $processor)
    {
        $processor->process($message);
        return back()->with('success', 'Pesan diparse.');
    }

    public function parsePending(MessageProcessor $processor)
    {
        $n = 0;
        TelegramMessage::where('parse_status', 'pending')->orderBy('message_date')
            ->chunkById(200, function ($chunk) use ($processor, &$n) {
                foreach ($chunk as $m) {
                    $processor->process($m);
                    $n++;
                }
            });
        return back()->with('success', "{$n} pesan diparse.");
    }

    public function ignore(TelegramMessage $message)
    {
        $message->update(['parse_status' => 'ignored']);
        return back();
    }
}
