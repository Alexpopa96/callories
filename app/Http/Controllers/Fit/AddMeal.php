<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Models\FavoriteFood;
use App\Services\Fit\DayStats;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AddMeal extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $date = DayStats::resolveDate($request->query('date'));

        $recent = $user->meals()->latest('id')->limit(60)->get()
            ->filter(fn ($meal) => ! empty($meal->items))
            ->unique('title')
            ->take(12)
            ->map(fn ($meal) => [
                'id' => $meal->id,
                'title' => $meal->title,
                'calories' => $meal->calories,
                'items' => $meal->items,
            ])
            ->values();

        return Inertia::render('Fit/AddMeal', [
            'date' => $date->toDateString(),
            'dateLabel' => DayStats::label($date),
            'isToday' => $date->isToday(),
            'recent' => $recent,
            'favorites' => $user->favoriteFoods()->orderBy('name')->get()->map(fn (FavoriteFood $food) => [
                'id' => $food->id,
                'name' => $food->name,
                'portion_grams' => $food->portion_grams,
                'calories' => $food->calories,
                'protein_g' => $food->protein_g,
                'carbs_g' => $food->carbs_g,
                'fat_g' => $food->fat_g,
                'fiber_g' => $food->fiber_g,
            ]),
        ]);
    }
}
