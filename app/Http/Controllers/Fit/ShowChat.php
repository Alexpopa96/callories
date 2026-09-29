<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ShowChat extends Controller
{
    public const PAGE = 50;

    public function __invoke(Request $request, int $user): Response|RedirectResponse
    {
        $me = $request->user();
        $friend = User::find($user);

        if (! $friend || ! $me->isFriendsWith($friend)) {
            return redirect('/friends')->with('error', 'Puteți vorbi doar dacă sunteți prieteni.');
        }

        self::markSeen($me, $friend);

        $messages = Message::between($me, $friend)->orderByDesc('id')->limit(self::PAGE + 1)->get();

        return Inertia::render('Fit/Chat', [
            'friend' => ['id' => $friend->id, 'name' => $friend->name],
            'messages' => $messages->take(self::PAGE)->reverse()->map->toChatArray($me)->values(),
            'hasMore' => $messages->count() > self::PAGE,
            'today' => now()->toDateString(),
        ]);
    }

    /**
     * Marks what the friend sent as read and remembers for a short while that the chat is open,
     * so new messages from them do not also trigger a push.
     */
    public static function markSeen(User $me, User $friend): void
    {
        Message::where('sender_id', $friend->id)->where('recipient_id', $me->id)->whereNull('read_at')->update(['read_at' => now()]);
        Cache::put(self::openKey($me, $friend), true, now()->addSeconds(20));
    }

    public static function openKey(User $viewer, User $other): string
    {
        return "chat-open:{$viewer->id}:{$other->id}";
    }
}
