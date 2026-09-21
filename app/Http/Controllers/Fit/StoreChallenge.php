<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Fit\ChallengePlanner;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreChallenge extends Controller
{
    public function __invoke(Request $request, ChallengePlanner $planner): RedirectResponse
    {
        $user = $request->user();

        if ($user->activeChallenge) {
            return back()->with('error', 'Ai deja o provocare activă. Abandon-o mai întâi ca să pornești una nouă.');
        }

        if (! $user->sex || ! $user->birth_date || ! $user->height_cm) {
            return back()->with('error', 'Completează mai întâi sexul, data nașterii și înălțimea din Profil.');
        }

        $data = $request->validate([
            'weightKg' => ['required', 'numeric', 'between:25,400'],
            'targetWeightKg' => ['required', 'numeric', 'between:25,400'],
            'days' => ['required', 'integer', 'between:7,180'],
            'goal' => ['required', Rule::in(ChallengePlanner::GOALS)],
        ]);

        $delta = $data['targetWeightKg'] - $data['weightKg'];

        if ($data['goal'] === 'lose_weight' && $delta > -1) {
            throw ValidationException::withMessages(['targetWeightKg' => 'Greutatea țintă trebuie să fie sub cea curentă.']);
        }

        if ($data['goal'] !== 'lose_weight' && $delta < 1) {
            throw ValidationException::withMessages(['targetWeightKg' => 'Greutatea țintă trebuie să fie peste cea curentă.']);
        }

        $plan = $planner->plan($user, (float) $data['weightKg'], (float) $data['targetWeightKg'], $data['days'], $data['goal']);
        $startedOn = CarbonImmutable::today();

        $user->weightLogs()->updateOrCreate(
            ['date' => $startedOn->toDateString()],
            ['weight_kg' => round((float) $data['weightKg'], 1)],
        );

        $user->challenges()->create([
            'goal' => $data['goal'],
            'status' => 'active',
            'start_weight_kg' => $data['weightKg'],
            'target_weight_kg' => $data['targetWeightKg'],
            'days' => $data['days'],
            'started_on' => $startedOn,
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

        return redirect('/challenge')->with('success', 'Provocarea a început.');
    }
}
