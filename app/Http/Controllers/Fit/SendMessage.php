<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use App\Services\Fit\PushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SendMessage extends Controller
{
    public function __invoke(Request $request, int $user, PushSender $sender): JsonResponse
    {
        $me = $request->user();
        $friend = User::find($user);

        abort_unless($friend && $me->isFriendsWith($friend), 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ], [
            'body.required' => 'Scrie un mesaj.',
            'body.max' => 'Mesajul poate avea maxim 2000 de caractere.',
        ]);

        $body = trim($data['body']);
        $message = Message::create(['sender_id' => $me->id, 'recipient_id' => $friend->id, 'body' => $body]);

        // no push while they are looking at this very conversation
        if (! Cache::has(ShowChat::openKey($friend, $me))) {
            // the text stays out of the notification, so it never shows on a lock screen or passes through Apple/Google
            defer(fn () => $sender->send($friend, [
                'title' => $me->name,
                'body' => 'Ți-a trimis un mesaj.',
                'url' => "/chat/{$me->id}",
            ]));
        }

        return response()->json(['message' => $message->toChatArray($me)], 201);
    }
}
