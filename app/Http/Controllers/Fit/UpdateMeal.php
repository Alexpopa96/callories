<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Fit\MealBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UpdateMeal extends Controller
{
    public function __invoke(Request $request, int $meal): RedirectResponse
    {
        $meal = $request->user()->meals()->findOrFail($meal);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            ...MealBuilder::itemRules(),
        ]);

        $meal->update(MealBuilder::attributes($data['items'], $data['title'] ?? null));

        return redirect('/today?date='.CarbonImmutable::parse($meal->eaten_on)->toDateString())->with('success', 'Masa a fost actualizată.');
    }
}
