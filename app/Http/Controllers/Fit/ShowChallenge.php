<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Services\Fit\ChallengeProgress;
use App\Services\Fit\DayStats;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShowChallenge extends Controller
{
    public function __invoke(Request $request, ChallengeProgress $progress): Response
    {
        $user = $request->user();
        $challenge = $user->activeChallenge;
        $challenge?->completeIfDue();
        $challenge?->refresh();

        return Inertia::render('Fit/Challenge', [
            'challenge' => ($challenge && $challenge->status === 'active') ? [
                'id' => $challenge->id,
                'goal' => $challenge->goal,
                'startedOn' => $challenge->started_on->toDateString(),
                'endsOn' => $challenge->ends_on->toDateString(),
                'targets' => $challenge->targets(),
                'adjusted' => $challenge->adjusted,
                'explanation' => $challenge->explanation,
                'progress' => $progress->forChallenge($challenge),
                'day' => $this->day($progress, $challenge, DayStats::resolveDate($request->query('date'))),
            ] : null,
            'latestWeightKg' => $user->latestWeight()?->weight_kg,
        ]);
    }

    private function day(ChallengeProgress $progress, Challenge $challenge, CarbonImmutable $date): array
    {
        $day = $progress->forDay($challenge, $date);
        $date = CarbonImmutable::parse($day['date']);

        return $day + [
            'label' => DayStats::label($date),
            'isToday' => $date->isToday(),
        ];
    }
}
