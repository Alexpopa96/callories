<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Fit\DayStats;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class History extends Controller
{
    public function __invoke(Request $request, DayStats $stats): Response
    {
        $user = $request->user();
        $today = CarbonImmutable::today();

        $days = collect($stats->range($user, $today->subDays(29), $today))->values();

        $lastWeek = $days->take(7);
        $average = fn (string $key) => (int) round($lastWeek->where($key, '>', 0)->avg($key) ?? 0);

        return Inertia::render('Fit/History', [
            'goals' => $user->goals(),
            'averages' => [
                'calories' => $average('calories'),
                'steps' => $average('steps'),
                'waterMl' => $average('water_ml'),
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
                    'meals' => $day['meals'],
                ];
            })->values(),
        ]);
    }
}
