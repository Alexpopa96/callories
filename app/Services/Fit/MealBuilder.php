<?php

namespace App\Services\Fit;

use Illuminate\Support\Str;

class MealBuilder
{
    /**
     * Validation rules for a list of meal items, shared by every endpoint that saves a meal.
     *
     * @return array<string, mixed>
     */
    public static function itemRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.name' => ['required', 'string', 'max:120'],
            'items.*.portion_grams' => ['required', 'numeric', 'min:0', 'max:5000'],
            'items.*.calories' => ['required', 'numeric', 'min:0', 'max:5000'],
            'items.*.protein_g' => ['required', 'numeric', 'min:0', 'max:500'],
            'items.*.carbs_g' => ['required', 'numeric', 'min:0', 'max:1000'],
            'items.*.fat_g' => ['required', 'numeric', 'min:0', 'max:500'],
            'items.*.fiber_g' => ['required', 'numeric', 'min:0', 'max:200'],
            'items.*.image_url' => ['nullable', 'string', 'max:500'],
            'items.*.barcode' => ['nullable', 'string', 'regex:/^\d{8,14}$/'],
        ];
    }

    /**
     * Normalised items plus the totals stored on the meal row.
     *
     * @param  array<int, array<string, mixed>>  $rawItems
     * @return array<string, mixed>
     */
    public static function attributes(array $rawItems, ?string $title = null): array
    {
        $items = collect($rawItems)->map(fn (array $item) => [
            'name' => $item['name'],
            'portion_grams' => round((float) $item['portion_grams']),
            'calories' => round((float) $item['calories']),
            'protein_g' => round((float) $item['protein_g'], 1),
            'carbs_g' => round((float) $item['carbs_g'], 1),
            'fat_g' => round((float) $item['fat_g'], 1),
            'fiber_g' => round((float) $item['fiber_g'], 1),
            ...(! empty($item['barcode']) ? ['barcode' => $item['barcode']] : []),
        ]);

        return [
            'title' => $title ?: Str::limit($items->pluck('name')->join(', '), 100),
            'items' => $items->all(),
            'calories' => (int) $items->sum('calories'),
            'protein_g' => round($items->sum('protein_g'), 1),
            'carbs_g' => round($items->sum('carbs_g'), 1),
            'fat_g' => round($items->sum('fat_g'), 1),
            'fiber_g' => round($items->sum('fiber_g'), 1),
        ];
    }
}
