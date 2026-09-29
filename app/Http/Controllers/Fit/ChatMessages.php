<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatMessages extends Controller
{
    /** Polled by the open chat: ?after=id for new messages, ?before=id for older ones. */
    public function __invoke(Request $request, int $user): JsonResponse
    {
        $me = $request->user();
        $friend = User::find($user);

        abort_unless($friend && $me->isFriendsWith($friend), 404);

        $data = $request->validate([
            'after' => ['nullable', 'integer', 'min:0'],
            'before' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Message::between($me, $friend);

        if (isset($data['before'])) {
            $older = $query->where('id', '<', $data['before'])->orderByDesc('id')->limit(ShowChat::PAGE + 1)->get();

            return response()->json([
                'messages' => $older->take(ShowChat::PAGE)->reverse()->map->toChatArray($me)->values(),
                'hasMore' => $older->count() > ShowChat::PAGE,
            ]);
        }

        ShowChat::markSeen($me, $friend);

        return response()->json([
            'messages' => $query->where('id', '>', $data['after'] ?? 0)->orderBy('id')->limit(100)->get()->map->toChatArray($me)->values(),
            // lets the sender's screen flip its bubbles to "Văzut"
            'readUpTo' => (int) Message::where('sender_id', $me->id)->where('recipient_id', $friend->id)->whereNotNull('read_at')->max('id'),
        ]);
    }
}
