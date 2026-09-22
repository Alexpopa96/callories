<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Fit\AssistantQuota;
use App\Services\Fit\DayStats;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Assistant extends Controller
{
    public function __invoke(Request $request, AssistantQuota $quota): Response
    {
        $user = $request->user();
        $today = DayStats::resolveDate(null)->toDateString();
        $meals = $user->meals()->where('eaten_on', $today)->get();
        $goals = $user->goals();

        return Inertia::render('Fit/Assistant', [
            'goals' => $goals,
            'remaining' => [
                'calories' => $goals['calories'] - (int) $meals->sum('calories'),
                'proteinG' => $goals['proteinG'] ? $goals['proteinG'] - round($meals->sum('protein_g'), 1) : null,
                'carbsG' => $goals['carbsG'] ? $goals['carbsG'] - round($meals->sum('carbs_g'), 1) : null,
                'fatG' => $goals['fatG'] ? $goals['fatG'] - round($meals->sum('fat_g'), 1) : null,
            ],
            'questionsLeft' => $quota->remaining($user),
        ]);
    }
}
