<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Fit\BarcodeLookup;
use App\Services\Fit\MealBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

use function Illuminate\Support\defer;

class UpdateMeal extends Controller
{
    public function __invoke(Request $request, int $meal, BarcodeLookup $barcodes): RedirectResponse
    {
        $meal = $request->user()->meals()->findOrFail($meal);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            ...MealBuilder::itemRules(),
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $meal->update([
            ...MealBuilder::attributes($data['items'], $data['title'] ?? null),
            ...(array_key_exists('notes', $data) ? ['notes' => $data['notes']] : []),
        ]);

        // a meal without its own photo gets the first product's picture, same as when it is saved
        $imageUrl = collect($data['items'])->pluck('image_url')->filter()->first();

        if (! $meal->photo_path && $imageUrl) {
            defer(function () use ($meal, $imageUrl, $barcodes) {
                if ($path = $barcodes->storeImage($imageUrl, "meals/{$meal->user_id}")) {
                    $meal->update(['photo_path' => $path]);
                }
            });
        }

        return redirect('/today?date='.CarbonImmutable::parse($meal->eaten_on)->toDateString())->with('success', 'Masa a fost actualizată.');
    }
}
