<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Services\Fit\ChallengePlanner;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UpdateChallenge extends Controller
{
    public function __invoke(Request $request, Challenge $challenge, ChallengePlanner $planner): RedirectResponse
    {
        abort_if($challenge->user_id !== $request->user()->id, 403);
        abort_if($challenge->status !== 'active', 404);

        $startedOn = CarbonImmutable::parse($challenge->started_on);
        $minDays = $startedOn->diffInDays(CarbonImmutable::today()) + 1;

        $data = $request->validate([
            'days' => ['required', 'integer', 'min:'.$minDays, 'max:180'],
        ], [
            'days.min' => "Nu poți scurta provocarea sub cele {$minDays} zile deja trecute.",
            'days.max' => 'Numărul de zile poate fi de maxim 180.',
        ]);

        $plan = $planner->plan(
            $challenge->user,
            (float) $challenge->start_weight_kg,
            (float) $challenge->target_weight_kg,
            $data['days'],
            $challenge->goal,
        );

        $challenge->update([
            'days' => $data['days'],
            'ends_on' => $startedOn->addDays($data['days']),
            'calorie_goal' => $plan['calories'],
            'protein_goal_g' => $plan['proteinG'],
            'carbs_goal_g' => $plan['carbsG'],
            'fat_goal_g' => $plan['fatG'],
            'water_goal_ml' => $plan['waterMl'],
            'daily_calorie_delta' => $plan['dailyCalorieDelta'],
            'adjusted' => $plan['adjusted'],
            'explanation' => $plan['explanation'],
        ]);

        return redirect('/challenge')->with('success', 'Numărul de zile a fost actualizat.');
    }
}
