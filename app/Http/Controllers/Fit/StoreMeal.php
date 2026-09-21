<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Fit\MealBuilder;
use App\Services\Fit\PushSender;
use App\Services\Fit\ReminderPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StoreMeal extends Controller
{
    public function __invoke(Request $request, ReminderPlanner $planner, PushSender $sender): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'title' => ['nullable', 'string', 'max:120'],
            'photo' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/gif,image/webp', 'max:5120'],
            ...MealBuilder::itemRules(),
            'confidence' => ['nullable', 'in:low,medium,high'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $attributes = MealBuilder::attributes($data['items'], $data['title'] ?? null);

        $previousTotal = $user->remind_calorie_limit && $data['date'] === CarbonImmutable::today()->toDateString()
            ? (int) $user->meals()->where('eaten_on', $data['date'])->sum('calories')
            : null;

        $user->meals()->create([
            ...$attributes,
            'eaten_on' => $data['date'],
            'photo_path' => $request->file('photo')?->store("meals/{$user->id}", 'public'),
            'confidence' => $data['confidence'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($previousTotal !== null) {
            $message = $planner->calorieLimitMessage($previousTotal, $previousTotal + (int) $attributes['calories'], (int) $user->calorie_goal);

            if ($message) {
                $sender->send($user, $message);
            }
        }

        return redirect('/today?date='.$data['date'])->with('success', 'Masa a fost salvată.');
    }
}
