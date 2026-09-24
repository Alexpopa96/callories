<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Fit\DayStats;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EditMeal extends Controller
{
    public function __invoke(Request $request, int $meal): Response
    {
        $meal = $request->user()->meals()->findOrFail($meal);
        $date = CarbonImmutable::parse($meal->eaten_on);

        return Inertia::render('Fit/EditMeal', [
            'meal' => [
                'id' => $meal->id,
                'title' => $meal->title,
                'date' => $date->toDateString(),
                'photoUrl' => $meal->photoUrl(),
                'items' => $meal->items ?? [],
                'calories' => $meal->calories,
                'notes' => $meal->notes,
            ],
            'dateLabel' => DayStats::label($date),
        ]);
    }
}
