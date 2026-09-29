<?php

namespace App\Services\Fit;

use App\Models\Message;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ChatInbox
{
    /**
     * One row per friend you have talked to, most recent conversation first.
     *
     * @return Collection<int, array{userId: int, name: string, unread: int, lastMessage: array{id: int, body: string, mine: bool, read: bool, when: string}}>
     */
    public function conversations(User $user, bool $hideText = false): Collection
    {
        $friendIds = $user->friendIds();

        if ($friendIds === []) {
            return collect();
        }

        // last message per conversation and unread counts, in a fixed number of queries whatever the number of friends
        $lastIds = Message::where('sender_id', $user->id)->orWhere('recipient_id', $user->id)
            ->selectRaw('MAX(id) as id')
            ->groupByRaw('CASE WHEN sender_id = ? THEN recipient_id ELSE sender_id END', [$user->id])
            ->pluck('id');
        $unreadByFriend = Message::where('recipient_id', $user->id)->whereNull('read_at')
            ->selectRaw('sender_id, COUNT(*) as unread')->groupBy('sender_id')->pluck('unread', 'sender_id');
        $names = User::whereIn('id', $friendIds)->pluck('name', 'id');

        return Message::whereIn('id', $lastIds)->orderByDesc('id')->get()
            ->map(function (Message $last) use ($user, $names, $unreadByFriend, $hideText) {
                $friendId = $last->sender_id === $user->id ? $last->recipient_id : $last->sender_id;

                // conversations with people who are no longer friends are closed, so they are not listed
                if (! $names->has($friendId)) {
                    return null;
                }

                return [
                    'userId' => $friendId,
                    'name' => $names[$friendId],
                    'unread' => (int) ($unreadByFriend[$friendId] ?? 0),
                    'lastMessage' => [
                        'id' => $last->id,
                        'body' => $hideText ? '🔒 Mesaj privat' : Str::limit(preg_replace('/\s+/', ' ', $last->body), 80),
                        'mine' => $last->sender_id === $user->id,
                        'read' => $last->read_at !== null,
                        'when' => self::when($last->created_at),
                    ],
                ];
            })
            ->filter()
            ->values();
    }

    /** WhatsApp style: time today, "Ieri", the weekday this week, a date before that. */
    public static function when(CarbonInterface $at): string
    {
        $at = $at->copy()->locale('ro');

        return match (true) {
            $at->isToday() => $at->format('H:i'),
            $at->isYesterday() => 'Ieri',
            $at->greaterThan(now()->subDays(6)->startOfDay()) => $at->isoFormat('dddd'),
            default => $at->format('d.m.y'),
        };
    }
}
