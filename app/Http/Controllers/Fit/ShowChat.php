<?php

namespace App\Http\Controllers\Fit;

use App\Events\MessagesRead;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use App\Services\Fit\Realtime;
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
        $unread = Message::where('sender_id', $friend->id)->where('recipient_id', $me->id)->whereNull('read_at');
        $upTo = (int) (clone $unread)->max('id');

        if ($upTo > 0) {
            $unread->where('id', '<=', $upTo)->update(['read_at' => now()]);
            Realtime::broadcast(new MessagesRead($me->id, $friend->id, $upTo));
        }

        // with a live connection the chat polls only as a fallback, every 20 seconds
        Cache::put(self::openKey($me, $friend), true, now()->addSeconds(35));
    }

    public static function openKey(User $viewer, User $other): string
    {
        return "chat-open:{$viewer->id}:{$other->id}";
    }
}
