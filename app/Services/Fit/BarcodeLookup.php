<?php

namespace App\Services\Fit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BarcodeLookup
{
    /**
     * Product from Open Food Facts as a meal item (values for one portion), or null when unknown.
     *
     * @return array{name: string, portion_grams: float, calories: float, protein_g: float, carbs_g: float, fat_g: float, fiber_g: float, per100: array<string, float>}|null
     */
    public function find(string $code): ?array
    {
        return Cache::remember("barcode:{$code}", now()->addDay(), function () use ($code) {
            $response = Http::timeout(8)
                ->withUserAgent('Calorii/1.0')
                ->get("https://world.openfoodfacts.org/api/v2/product/{$code}.json", [
                    'fields' => 'product_name,brands,nutriments,serving_quantity',
                ]);

            if (! $response->successful() || $response->json('status') !== 1) {
                return null;
            }

            $product = $response->json('product') ?? [];
            $nutriments = $product['nutriments'] ?? [];

            $per100 = [
                'calories' => $this->number($nutriments['energy-kcal_100g'] ?? null),
                'protein_g' => $this->number($nutriments['proteins_100g'] ?? null),
                'carbs_g' => $this->number($nutriments['carbohydrates_100g'] ?? null),
                'fat_g' => $this->number($nutriments['fat_100g'] ?? null),
                'fiber_g' => $this->number($nutriments['fiber_100g'] ?? null),
            ];

            $name = trim(($product['product_name'] ?? '').(! empty($product['brands']) ? ' — '.explode(',', $product['brands'])[0] : ''));

            if ($name === '' || ($per100['calories'] === 0.0 && ! isset($nutriments['energy-kcal_100g']))) {
                return null;
            }

            $portion = (float) ($product['serving_quantity'] ?? 0);
            $portion = $portion > 0 && $portion <= 2000 ? $portion : 100.0;

            return [
                'name' => $name,
                'portion_grams' => $portion,
                ...array_map(fn (float $value) => round($value * $portion / 100, 1), $per100),
                'per100' => $per100,
            ];
        });
    }

    private function number(mixed $value): float
    {
        return is_numeric($value) ? max(0.0, (float) $value) : 0.0;
    }
}
