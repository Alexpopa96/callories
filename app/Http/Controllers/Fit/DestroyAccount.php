<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DestroyAccount extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']], [
            'password.required' => 'Introdu parola ca să confirmi.',
            'password.current_password' => 'Parola nu este corectă.',
        ]);

        $user = $request->user();

        Storage::disk('public')->deleteDirectory("meals/{$user->id}");
        $user->tokens()->delete();

        Auth::guard('web')->logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
