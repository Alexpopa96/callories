<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DismissAdaptiveGoal extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        $adjustment = $user->goalAdjustments()->where('status', 'pending')->latest()->first();

        $adjustment?->update(['status' => 'dismissed', 'resolved_at' => now()]);

        return back()->with('success', 'Ajustarea a fost respinsă.');
    }
}
