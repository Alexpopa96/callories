<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Fit\GoalCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApplyGoals extends Controller
{
    public function __invoke(Request $request, GoalCalculator $calculator): RedirectResponse
    {
        $user = $request->user();
        $suggestion = $calculator->suggest($user, $user->latestWeight()?->weight_kg);

        if (! $suggestion) {
            return back()->with('error', 'Completează sexul, data nașterii, înălțimea și greutatea.');
        }

        $user->update([
            'calorie_goal' => $suggestion['calories'],
            'protein_goal_g' => $suggestion['proteinG'],
            'carbs_goal_g' => $suggestion['carbsG'],
            'fat_goal_g' => $suggestion['fatG'],
            'water_goal_ml' => $suggestion['waterMl'],
        ]);

        return back()->with('success', 'Obiectivele au fost actualizate.');
    }
}
