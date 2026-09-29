<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RegenerateFriendCode extends Controller
{
    /** Invalidates links already shared; existing friends and requests are kept. */
    public function __invoke(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['friend_code' => User::newFriendCode()])->save();

        return back()->with('success', 'Ai un cod nou. Linkurile vechi nu mai funcționează.');
    }
}
