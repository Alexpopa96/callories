<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Models\Friendship;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class Friends extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $friendships = Friendship::involving($user)->with('user', 'friend')->latest('updated_at')->get();

        // only the name leaves the server: friends never see each other's email
        $row = fn (Friendship $friendship) => [
            'id' => $friendship->id,
            'userId' => $friendship->otherThan($user)->id,
            'name' => $friendship->otherThan($user)->name,
            'since' => ($friendship->accepted_at ?? $friendship->created_at)->locale('ro')->isoFormat('D MMM YYYY'),
        ];

        $impersonating = $request->session()->has('impersonate');

        // last message and unread count per conversation, in two queries whatever the number of friends
        $lastIds = Message::where('sender_id', $user->id)->orWhere('recipient_id', $user->id)
            ->selectRaw('MAX(id) as id')
            ->groupByRaw('CASE WHEN sender_id = ? THEN recipient_id ELSE sender_id END', [$user->id])
            ->pluck('id');
        $lastByFriend = Message::whereIn('id', $lastIds)->get()
            ->keyBy(fn (Message $message) => $message->sender_id === $user->id ? $message->recipient_id : $message->sender_id);
        $unreadByFriend = Message::where('recipient_id', $user->id)->whereNull('read_at')
            ->selectRaw('sender_id, COUNT(*) as unread')->groupBy('sender_id')->pluck('unread', 'sender_id');

        $friends = $friendships->where('status', 'accepted')
            ->map(function (Friendship $friendship) use ($row, $user, $lastByFriend, $unreadByFriend, $impersonating) {
                $friendId = $friendship->otherThan($user)->id;
                $last = $lastByFriend->get($friendId);

                return $row($friendship) + [
                    'lastMessage' => $last ? [
                        'id' => $last->id,
                        'body' => $impersonating ? '🔒 Mesaj privat' : Str::limit($last->body, 60),
                        'mine' => $last->sender_id === $user->id,
                        'when' => $last->created_at->isToday() ? $last->created_at->format('H:i') : $last->created_at->locale('ro')->isoFormat('D MMM'),
                    ] : null,
                    'unread' => (int) ($unreadByFriend[$friendId] ?? 0),
                ];
            })
            // most recent conversations first, friends you never talked to after them
            ->sortByDesc(fn (array $friend) => $friend['lastMessage']['id'] ?? 0)
            ->values();

        $code = $request->query('code');

        return Inertia::render('Fit/Friends', [
            'code' => $user->friendCode(),
            'inviteUrl' => url('/friends?code='.$user->friendCode()),
            'prefillCode' => is_string($code) && $code !== $user->friendCode() ? strtoupper(substr($code, 0, 8)) : null,
            'friends' => $friends,
            'incoming' => $friendships->where('status', 'pending')->where('friend_id', $user->id)->map($row)->values(),
            'outgoing' => $friendships->where('status', 'pending')->where('user_id', $user->id)->map($row)->values(),
            'blocked' => $user->blocks()->with('blocked')->latest()->get()->map(fn ($block) => [
                'userId' => $block->blocked_id,
                'name' => $block->blocked->name,
            ]),
        ]);
    }
}
