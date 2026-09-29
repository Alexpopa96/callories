<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use App\Services\Fit\PushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReportMessage extends Controller
{
    public function __invoke(Request $request, int $message, PushSender $sender): JsonResponse
    {
        $me = $request->user();

        // only messages you received can be reported
        $message = Message::where('recipient_id', $me->id)->findOrFail($message);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $report = MessageReport::firstOrCreate(
            ['reporter_id' => $me->id, 'message_id' => $message->id],
            ['body' => $message->body, 'reason' => $data['reason'] ?? null],
        );

        if ($report->wasRecentlyCreated) {
            Log::warning('Message reported', ['report' => $report->id, 'message' => $message->id, 'sender' => $message->sender_id, 'reporter' => $me->id]);

            $admins = User::whereHas('roles.permissions', fn ($q) => $q->where('name', 'view users'))->get();
            defer(fn () => $admins->each(fn (User $admin) => $sender->send($admin, [
                'title' => 'Mesaj raportat',
                'body' => "Raport #{$report->id} de la {$me->name}",
                'url' => '/today',
            ])));
        }

        return response()->json(['ok' => true]);
    }
}
