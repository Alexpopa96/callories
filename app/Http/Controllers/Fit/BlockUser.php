<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BlockUser extends Controller
{
    public function __invoke(Request $request, int $user): RedirectResponse
    {
        $me = $request->user();

        // you can only block someone you are connected to, so ids of strangers cannot be probed
        $friendship = Friendship::between($me, User::findOrFail($user))->firstOrFail();
        $other = $friendship->otherThan($me);

        $friendship->delete();
        $me->blocks()->firstOrCreate(['blocked_id' => $other->id]);

        // also used from inside the chat, which no longer exists after blocking
        return redirect('/friends')->with('success', "{$other->name} a fost blocat.");
    }
}
