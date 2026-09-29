<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Models\Friendship;
use App\Services\Fit\PushSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

use function Illuminate\Support\defer;

class AcceptFriend extends Controller
{
    public function __invoke(Request $request, int $friendship, PushSender $sender): RedirectResponse
    {
        $user = $request->user();

        // only the one who received the request can accept it
        $friendship = Friendship::where('friend_id', $user->id)->where('status', 'pending')->findOrFail($friendship);
        $friendship->update(['status' => 'accepted', 'accepted_at' => now()]);

        $requester = $friendship->user;
        defer(fn () => $requester->notify_messages && $sender->send($requester, [
            'title' => 'Cerere acceptată',
            'body' => "{$user->name} ți-a acceptat cererea de prietenie.",
            'url' => '/friends',
        ]));

        return back()->with('success', "Acum ești prieten cu {$requester->name}.");
    }
}
