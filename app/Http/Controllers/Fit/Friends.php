<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Models\Friendship;
use Illuminate\Http\Request;
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

        $code = $request->query('code');

        return Inertia::render('Fit/Friends', [
            'code' => $user->friendCode(),
            'inviteUrl' => url('/friends?code='.$user->friendCode()),
            'prefillCode' => is_string($code) && $code !== $user->friendCode() ? strtoupper(substr($code, 0, 8)) : null,
            'friends' => $friendships->where('status', 'accepted')->map($row)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values(),
            'incoming' => $friendships->where('status', 'pending')->where('friend_id', $user->id)->map($row)->values(),
            'outgoing' => $friendships->where('status', 'pending')->where('user_id', $user->id)->map($row)->values(),
            'blocked' => $user->blocks()->with('blocked')->latest()->get()->map(fn ($block) => [
                'userId' => $block->blocked_id,
                'name' => $block->blocked->name,
            ]),
        ]);
    }
}
