<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoreMeal extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'title' => ['nullable', 'string', 'max:120'],
            'photo' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/gif,image/webp', 'max:5120'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.name' => ['required', 'string', 'max:120'],
            'items.*.portion_grams' => ['required', 'numeric', 'min:0', 'max:5000'],
            'items.*.calories' => ['required', 'numeric', 'min:0', 'max:5000'],
            'items.*.protein_g' => ['required', 'numeric', 'min:0', 'max:500'],
            'items.*.carbs_g' => ['required', 'numeric', 'min:0', 'max:1000'],
            'items.*.fat_g' => ['required', 'numeric', 'min:0', 'max:500'],
            'items.*.fiber_g' => ['required', 'numeric', 'min:0', 'max:200'],
            'confidence' => ['nullable', 'in:low,medium,high'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $items = collect($data['items'])->map(fn (array $item) => [
            'name' => $item['name'],
            'portion_grams' => round((float) $item['portion_grams']),
            'calories' => round((float) $item['calories']),
            'protein_g' => round((float) $item['protein_g'], 1),
            'carbs_g' => round((float) $item['carbs_g'], 1),
            'fat_g' => round((float) $item['fat_g'], 1),
            'fiber_g' => round((float) $item['fiber_g'], 1),
        ]);

        $user->meals()->create([
            'eaten_on' => $data['date'],
            'title' => $data['title'] ?? Str::limit($items->pluck('name')->join(', '), 100),
            'photo_path' => $request->file('photo')?->store("meals/{$user->id}", 'public'),
            'items' => $items->all(),
            'calories' => (int) $items->sum('calories'),
            'protein_g' => round($items->sum('protein_g'), 1),
            'carbs_g' => round($items->sum('carbs_g'), 1),
            'fat_g' => round($items->sum('fat_g'), 1),
            'fiber_g' => round($items->sum('fiber_g'), 1),
            'confidence' => $data['confidence'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect('/today?date='.$data['date'])->with('success', 'Masa a fost salvată.');
    }
}
