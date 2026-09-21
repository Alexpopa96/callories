<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Fit\GoalCalculator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Profile extends Controller
{
    public function __invoke(Request $request, GoalCalculator $calculator): Response
    {
        $user = $request->user();
        $weight = $user->latestWeight()?->weight_kg;

        return Inertia::render('Fit/Profile', [
            'goals' => $user->goals(),
            'canAdmin' => $user->can('view dashboard'),
            'body' => [
                'sex' => $user->sex,
                'birthDate' => $user->birth_date?->toDateString(),
                'heightCm' => $user->height_cm,
                'activityLevel' => $user->activity_level,
                'goalType' => $user->goal_type,
                'weightKg' => $weight,
            ],
            'suggestion' => $calculator->suggest($user, $weight),
            'reminders' => [
                'meals' => $user->remind_meals,
                'water' => $user->remind_water,
                'calorieLimit' => $user->remind_calorie_limit,
                'challenge' => $user->remind_challenge,
            ],
            'pushKey' => config('services.webpush.public_key'),
        ]);
    }
}
