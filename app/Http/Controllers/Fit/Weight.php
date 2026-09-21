<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Weight extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $logs = $user->weightLogs()
            ->where('date', '>=', CarbonImmutable::today()->subDays(365)->toDateString())
            ->orderBy('date')
            ->get()
            ->map(function ($log) {
                $date = CarbonImmutable::parse($log->date);

                return [
                    'id' => $log->id,
                    'date' => $date->toDateString(),
                    'label' => $date->locale('ro')->isoFormat('D MMM YYYY'),
                    'weightKg' => $log->weight_kg,
                ];
            })
            ->values();

        return Inertia::render('Fit/Weight', [
            'logs' => $logs,
            'latest' => $logs->last()['weightKg'] ?? null,
            'change' => $logs->count() > 1 ? round($logs->last()['weightKg'] - $logs->first()['weightKg'], 1) : null,
            'goalType' => $user->goal_type,
            'today' => CarbonImmutable::today()->toDateString(),
        ]);
    }
}
