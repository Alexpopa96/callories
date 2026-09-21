<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Fit\DayStats;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class History extends Controller
{
    public function __invoke(Request $request, DayStats $stats): Response
    {
        $user = $request->user();
        $today = CarbonImmutable::today();
        $goals = $user->goals();

        $days = collect($stats->range($user, $today->subDays(29), $today))->values();

        $average = fn (Collection $set, string $key) => (int) round($set->where($key, '>', 0)->avg($key) ?? 0);
        $averages = fn (Collection $set) => [
            'calories' => $average($set, 'calories'),
            'steps' => $average($set, 'steps'),
            'waterMl' => $average($set, 'water_ml'),
            'protein' => $average($set, 'protein_g'),
        ];

        return Inertia::render('Fit/History', [
            'goals' => $goals,
            'averages' => $averages($days->take(7)),
            'averages30' => $averages($days),
            'hits' => [
                'calories' => $days->filter(fn ($day) => $day['calories'] >= $goals['calories'] * 0.9 && $day['calories'] <= $goals['calories'] * 1.1)->count(),
                'steps' => $days->filter(fn ($day) => $day['steps'] >= $goals['steps'])->count(),
                'waterMl' => $days->filter(fn ($day) => $day['water_ml'] >= $goals['waterMl'])->count(),
            ],
            'days' => $days->map(function (array $day) {
                $date = CarbonImmutable::parse($day['date']);

                return [
                    'date' => $day['date'],
                    'weekday' => $date->locale('ro')->isoFormat('ddd'),
                    'label' => $date->locale('ro')->isoFormat('D MMM'),
                    'calories' => $day['calories'],
                    'steps' => $day['steps'],
                    'waterMl' => $day['water_ml'],
                    'protein' => $day['protein_g'],
                    'meals' => $day['meals'],
                ];
            })->values(),
        ]);
    }
}
