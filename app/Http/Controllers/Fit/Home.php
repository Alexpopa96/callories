<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Models\Meal;
use App\Services\Fit\DayStats;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Home extends Controller
{
    public function __invoke(Request $request, DayStats $stats): Response
    {
        $user = $request->user();
        $date = DayStats::resolveDate($request->query('date'));
        $today = CarbonImmutable::today();

        $meals = $user->meals()->where('eaten_on', $date->toDateString())->orderBy('id')->get();
        $log = $user->dailyLogs()->where('date', $date->toDateString())->first();

        $from = $today->subDays(9)->min($date);
        $strip = collect($stats->range($user, $from, $today))
            ->reverse()
            ->map(fn (array $day) => [
                'date' => $day['date'],
                'weekday' => CarbonImmutable::parse($day['date'])->locale('ro')->isoFormat('ddd'),
                'day' => CarbonImmutable::parse($day['date'])->day,
                'hasData' => $day['meals'] > 0 || $day['steps'] > 0 || $day['water_ml'] > 0,
            ])
            ->values();

        return Inertia::render('Fit/Home', [
            'date' => $date->toDateString(),
            'dateLabel' => DayStats::label($date),
            'isToday' => $date->isSameDay($today),
            'strip' => $strip,
            'goals' => $user->goals(),
            'weightKg' => $user->latestWeight()?->weight_kg,
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
