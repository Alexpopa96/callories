<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Models\Friendship;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DestroyFriend extends Controller
{
    /** Declines, cancels or ends a friendship, depending on its state and side; silent for the other person. */
    public function __invoke(Request $request, int $friendship): RedirectResponse
    {
        $user = $request->user();
        $friendship = Friendship::involving($user)->findOrFail($friendship);

        $message = match (true) {
            $friendship->status === 'accepted' => 'Prietenul a fost eliminat.',
            $friendship->user_id === $user->id => 'Cererea a fost anulată.',
            default => 'Cererea a fost refuzată.',
        };

        $friendship->delete();

        return back()->with('success', $message);
    }
}
