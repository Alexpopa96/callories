<?php

namespace App\Http\Controllers\Fit;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use App\Services\Fit\PushSender;
use App\Services\Fit\Realtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

use function Illuminate\Support\defer;

class SendMessage extends Controller
{
    public function __invoke(Request $request, int $user, PushSender $sender): JsonResponse
    {
        $me = $request->user();
        $friend = User::find($user);

        abort_unless($friend && $me->isFriendsWith($friend), 404);

        $data = $request->validate([
            'body' => ['required_without:meal_id', 'nullable', 'string', 'max:2000'],
            'meal_id' => ['nullable', 'integer'],
            'client_id' => ['nullable', 'uuid'],
        ], [
            'body.required_without' => 'Scrie un mesaj.',
            'body.max' => 'Mesajul poate avea maxim 2000 de caractere.',
        ]);

        $body = trim($data['body'] ?? '');
        // only your own meals can be shared
        $meal = isset($data['meal_id']) ? $me->meals()->findOrFail($data['meal_id']) : null;

        // a retry of a send that already went through returns the saved message instead of a copy
        if (isset($data['client_id'])) {
            $existing = Message::where('sender_id', $me->id)->where('client_id', $data['client_id'])->first();

            if ($existing) {
                return response()->json(['message' => $existing->toChatArray($me)]);
            }
        }

        $message = Message::create([
            'sender_id' => $me->id,
            'recipient_id' => $friend->id,
            'client_id' => $data['client_id'] ?? null,
            'body' => $body,
            'meal' => $meal ? Message::mealSnapshot($meal) : null,
        ]);

        Realtime::broadcast(new MessageSent($message));

        // no push while they are looking at this very conversation
        if ($friend->notify_messages && ! Cache::has(ShowChat::openKey($friend, $me))) {
            // the text stays out of the notification, so it never shows on a lock screen or passes through Apple/Google
            defer(fn () => $sender->send($friend, [
                'title' => $me->name,
                'body' => $meal ? 'Ți-a trimis o masă.' : 'Ți-a trimis un mesaj.',
                'url' => "/chat/{$me->id}",
            ]));
        }

        return response()->json(['message' => $message->toChatArray($me)], 201);
    }
}
