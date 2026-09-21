<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AbandonChallenge extends Controller
{
    public function __invoke(Request $request, Challenge $challenge): RedirectResponse
    {
        abort_if($challenge->user_id !== $request->user()->id, 403);

        if ($challenge->status === 'active') {
            $challenge->update(['status' => 'abandoned', 'abandoned_at' => now()]);
        }

        return redirect('/challenge')->with('success', 'Provocarea a fost abandonată.');
    }
}
