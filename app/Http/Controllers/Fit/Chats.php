<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Fit\ChatInbox;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Chats extends Controller
{
    public function __invoke(Request $request, ChatInbox $inbox): Response
    {
        $user = $request->user();

        return Inertia::render('Fit/Chats', [
            'chats' => fn () => $inbox->conversations($user, $request->session()->has('impersonate')),
            // everyone you can start a conversation with, for the "new chat" sheet
            'friends' => fn () => User::whereIn('id', $user->friendIds())->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $friend) => ['userId' => $friend->id, 'name' => $friend->name]),
        ]);
    }
}
