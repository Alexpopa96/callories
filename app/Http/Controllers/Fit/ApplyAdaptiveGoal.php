<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApplyAdaptiveGoal extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        $adjustment = $user->goalAdjustments()->where('status', 'pending')->latest()->first();

        if (! $adjustment) {
            return back()->with('error', 'Nu există nicio ajustare de aplicat.');
        }

        $user->update([
            'calorie_goal' => $adjustment->suggested_calories,
            'protein_goal_g' => $adjustment->suggested_macros['proteinG'],
            'carbs_goal_g' => $adjustment->suggested_macros['carbsG'],
            'fat_goal_g' => $adjustment->suggested_macros['fatG'],
            'water_goal_ml' => $adjustment->suggested_macros['waterMl'],
        ]);

        $adjustment->update(['status' => 'applied', 'resolved_at' => now()]);

        return back()->with('success', 'Obiectivele au fost ajustate.');
    }
}
