<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Fit\GoalCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UpdateBody extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sex' => ['required', 'in:m,f'],
            'birthDate' => ['required', 'date_format:Y-m-d', 'after:1900-01-01', 'before:'.CarbonImmutable::today()->subYears(10)->toDateString()],
            'heightCm' => ['required', 'integer', 'between:100,250'],
            'activityLevel' => ['required', Rule::in(array_keys(GoalCalculator::ACTIVITY))],
            'goalType' => ['required', Rule::in(GoalCalculator::GOALS)],
            'weightKg' => ['nullable', 'numeric', 'between:25,400'],
        ]);

        $user = $request->user();

        $user->update([
            'sex' => $data['sex'],
            'birth_date' => $data['birthDate'],
            'height_cm' => $data['heightCm'],
            'activity_level' => $data['activityLevel'],
            'goal_type' => $data['goalType'],
        ]);

        if (isset($data['weightKg'])) {
            $user->weightLogs()->updateOrCreate(
                ['date' => CarbonImmutable::today()->toDateString()],
                ['weight_kg' => round((float) $data['weightKg'], 1)],
            );
        }

        return back()->with('success', 'Datele au fost salvate.');
    }
}
