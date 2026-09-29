<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Models\Friendship;
use App\Models\User;
use App\Services\Fit\PushSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\defer;

class StoreFriend extends Controller
{
    public function __invoke(Request $request, PushSender $sender): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $data['code']));

        $other = User::where('friend_code', $code)->first();

        // a block looks exactly like a wrong code, so nobody learns they were blocked
        if (! $other || $other->is($user) || $user->blockedEitherWay($other)) {
            throw ValidationException::withMessages(['code' => $other?->is($user) ? 'Acesta e codul tău.' : 'Codul nu există.']);
        }

        $existing = Friendship::between($user, $other)->first();

        if ($existing?->status === 'accepted') {
            return redirect('/friends')->with('success', "Ești deja prieten cu {$other->name}.");
        }

        if ($existing && $existing->user_id === $user->id) {
            return redirect('/friends')->with('success', 'Cererea e deja trimisă.');
        }

        // they already asked us: adding their code simply accepts
        if ($existing) {
            $existing->update(['status' => 'accepted', 'accepted_at' => now()]);
            defer(fn () => $other->notify_messages && $sender->send($other, [
                'title' => 'Cerere acceptată',
                'body' => "{$user->name} ți-a acceptat cererea de prietenie.",
                'url' => '/friends',
            ]));

            return redirect('/friends')->with('success', "Acum ești prieten cu {$other->name}.");
        }

        Friendship::create(['user_id' => $user->id, 'friend_id' => $other->id]);
        defer(fn () => $other->notify_messages && $sender->send($other, [
            'title' => 'Cerere de prietenie',
            'body' => "{$user->name} vrea să fiți prieteni.",
            'url' => '/friends',
        ]));

        return redirect('/friends')->with('success', "Cerere trimisă către {$other->name}.");
    }
}
