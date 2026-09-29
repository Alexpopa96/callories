<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Fit\DayStats;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EditMeal extends Controller
{
    public function __invoke(Request $request, int $meal): Response
    {
        $user = $request->user();
        $meal = $user->meals()->findOrFail($meal);
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
            // who the meal can be sent to; chats stay closed while an admin impersonates
            'friends' => $request->session()->has('impersonate') ? [] : User::whereIn('id', $user->friendIds())->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $friend) => ['id' => $friend->id, 'name' => $friend->name]),
        ]);
    }
}
