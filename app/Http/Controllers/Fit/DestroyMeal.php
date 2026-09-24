<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DestroyMeal extends Controller
{
    public function __invoke(Request $request, int $meal): RedirectResponse
    {
        $meal = $request->user()->meals()->findOrFail($meal);

        if ($meal->photo_path) {
            Storage::disk('public')->delete($meal->photo_path);
        }

        // pictures uploaded for single products of this meal
        $itemPhotos = collect($meal->items)->pluck('image_url')
            ->filter(fn ($url) => str_starts_with((string) $url, "/storage/meals/{$meal->user_id}/items/"))
            ->map(fn (string $url) => substr($url, strlen('/storage/')));

        if ($itemPhotos->isNotEmpty()) {
            Storage::disk('public')->delete($itemPhotos->all());
        }

        $meal->delete();

        return back()->with('success', 'Masa a fost ștearsă.');
    }
}
