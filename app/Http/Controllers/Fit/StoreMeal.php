<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Fit\BarcodeLookup;
use App\Services\Fit\MealBuilder;
use App\Services\Fit\PushSender;
use App\Services\Fit\ReminderPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

use function Illuminate\Support\defer;

class StoreMeal extends Controller
{
    public function __invoke(Request $request, ReminderPlanner $planner, PushSender $sender, BarcodeLookup $barcodes): RedirectResponse
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

        $meal = $user->meals()->create([
            ...$attributes,
            'eaten_on' => $data['date'],
            'photo_path' => $request->file('photo')?->store("meals/{$user->id}", 'public'),
            'confidence' => $data['confidence'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        // a meal of scanned products without its own photo gets the first product's picture,
        // downloaded after the response since the Open Food Facts image server can be slow
        $imageUrl = collect($data['items'])->pluck('image_url')->first(fn ($url) => str_starts_with((string) $url, 'https://images.openfoodfacts.org/'));

        if (! $meal->photo_path && $imageUrl) {
            defer(function () use ($meal, $imageUrl, $barcodes) {
                if ($path = $barcodes->storeImage($imageUrl, "meals/{$meal->user_id}")) {
                    $meal->update(['photo_path' => $path]);
                }
            });
        }

        if ($previousTotal !== null) {
            $message = $planner->calorieLimitMessage($previousTotal, $previousTotal + (int) $attributes['calories'], (int) $user->calorie_goal);

            if ($message) {
                $sender->send($user, $message);
            }
        }

        return redirect('/today?date='.$data['date'])->with('success', 'Masa a fost salvată.');
    }
}
