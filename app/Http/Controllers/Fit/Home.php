<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Models\Meal;
use App\Services\Fit\ChallengeProgress;
use App\Services\Fit\DayStats;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Home extends Controller
{
    public function __invoke(Request $request, DayStats $stats, ChallengeProgress $challengeProgress): Response
    {
        $user = $request->user();
        $date = DayStats::resolveDate($request->query('date'));
        $today = CarbonImmutable::today();

        $challenge = $user->activeChallenge;
        $challenge?->completeIfDue();
        $challenge?->refresh();

        $meals = $user->meals()->where('eaten_on', $date->toDateString())->orderBy('id')->get();
        $log = $user->dailyLogs()->where('date', $date->toDateString())->first();

        // the calendar week (Monday to Sunday) that contains the selected day
        $weekStart = $date->startOfWeek(CarbonInterface::MONDAY);
        $weekEnd = $weekStart->addDays(6);
        $strip = collect($stats->range($user, $weekStart, $weekEnd))
            ->reverse()
            ->map(fn (array $day) => [
                'date' => $day['date'],
                'weekday' => CarbonImmutable::parse($day['date'])->locale('ro')->isoFormat('ddd'),
                'day' => CarbonImmutable::parse($day['date'])->day,
                'hasData' => $day['meals'] > 0 || $day['steps'] > 0 || $day['water_ml'] > 0,
                'future' => $day['date'] > $today->toDateString(),
            ])
            ->values();

        $challengeSummary = null;

        if ($challenge && $challenge->status === 'active') {
            $progress = $challengeProgress->forChallenge($challenge);
            $challengeSummary = [
                'goal' => $challenge->goal,
                'daysElapsed' => $progress['daysElapsed'],
                'totalDays' => $progress['totalDays'],
                'pctDays' => $progress['pctDays'],
                'calorieGoal' => $challenge->calorie_goal,
                'startWeightKg' => $progress['startWeightKg'],
                'targetWeightKg' => $progress['targetWeightKg'],
                'currentWeightKg' => $progress['currentWeightKg'],
                'pctWeight' => $progress['pctWeight'],
            ];
        }

        return Inertia::render('Fit/Home', [
            'date' => $date->toDateString(),
            'dateLabel' => DayStats::label($date),
            'isToday' => $date->isSameDay($today),
            'strip' => $strip,
            'week' => [
                'label' => $weekStart->isSameMonth($weekEnd)
                    ? $weekStart->day.'–'.$weekEnd->locale('ro')->isoFormat('D MMM')
                    : $weekStart->locale('ro')->isoFormat('D MMM').' – '.$weekEnd->locale('ro')->isoFormat('D MMM'),
                'prev' => $date->subWeek()->toDateString(),
                'next' => $weekEnd->lt($today) ? $date->addWeek()->min($today)->toDateString() : null,
            ],
            'goals' => $user->goals(),
            'weightKg' => $user->latestWeight()?->weight_kg,
            'challenge' => $challengeSummary,
            'totals' => [
                'calories' => (int) $meals->sum('calories'),
                'protein' => round($meals->sum('protein_g'), 1),
                'carbs' => round($meals->sum('carbs_g'), 1),
                'fat' => round($meals->sum('fat_g'), 1),
                'fiber' => round($meals->sum('fiber_g'), 1),
            ],
            'steps' => $log?->steps ?? 0,
            'waterMl' => $log?->water_ml ?? 0,
            'meals' => $meals->map(fn (Meal $meal) => [
                'id' => $meal->id,
                'title' => $meal->title,
                'time' => $meal->created_at->format('H:i'),
                'photoUrl' => $meal->photoUrl(),
                'calories' => $meal->calories,
                'protein' => $meal->protein_g,
                'carbs' => $meal->carbs_g,
                'fat' => $meal->fat_g,
                'fiber' => $meal->fiber_g,
            ])->values(),
        ]);
    }
}
