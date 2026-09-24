<?php

namespace App\Services\Fit;

use App\Models\CustomBarcode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BarcodeLookup
{
    private const IMAGE_HOST = 'images.openfoodfacts.org';

    /**
     * Product from Open Food Facts, or from products added by users when Open Food Facts
     * doesn't have it (own-brand items from local stores are often missing there).
     * Values are for one portion, or null when unknown everywhere.
     *
     * @return array{name: string, portion_grams: float, calories: float, protein_g: float, carbs_g: float, fat_g: float, fiber_g: float, per100: array<string, float>, image_url: ?string, barcode: string}|null
     */
    public function find(string $code): ?array
    {
        return Cache::remember("barcode:v2:{$code}", now()->addDay(), function () use ($code) {
            return $this->fromOpenFoodFacts($code) ?? $this->fromCustom($code);
        });
    }

    /**
     * @return array{name: string, portion_grams: float, calories: float, protein_g: float, carbs_g: float, fat_g: float, fiber_g: float, per100: array<string, float>, image_url: ?string, barcode: string}|null
     */
    private function fromCustom(string $code): ?array
    {
        $product = CustomBarcode::where('code', $code)->first();

        if (! $product) {
            return null;
        }

        $per100 = [
            'calories' => $product->calories,
            'protein_g' => $product->protein_g,
            'carbs_g' => $product->carbs_g,
            'fat_g' => $product->fat_g,
            'fiber_g' => $product->fiber_g,
        ];

        $factor = $product->portion_grams > 0 ? 100 / $product->portion_grams : 1;

        return [
            'name' => $product->name,
            'portion_grams' => (float) $product->portion_grams,
            'calories' => (float) $product->calories,
            'protein_g' => (float) $product->protein_g,
            'carbs_g' => (float) $product->carbs_g,
            'fat_g' => (float) $product->fat_g,
            'fiber_g' => (float) $product->fiber_g,
            'per100' => array_map(fn (float $value) => round($value * $factor, 1), $per100),
            'image_url' => null,
            'barcode' => $code,
        ];
    }

    /**
     * @return array{name: string, portion_grams: float, calories: float, protein_g: float, carbs_g: float, fat_g: float, fiber_g: float, per100: array<string, float>, image_url: ?string, barcode: string}|null
     */
    private function fromOpenFoodFacts(string $code): ?array
    {
        $response = Http::timeout(8)
            ->withUserAgent('KaloMind/1.0')
            ->get("https://world.openfoodfacts.org/api/v2/product/{$code}.json", [
                'fields' => 'product_name,brands,nutriments,serving_quantity,image_front_small_url,image_front_url',
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
            'image_url' => $this->imageUrl($product['image_front_small_url'] ?? $product['image_front_url'] ?? null),
            'barcode' => $code,
        ];
    }

    /**
     * Picture of the best Open Food Facts match for a product name, for meals saved before
     * the barcode was kept on each item.
     */
    public function imageForName(string $name): ?string
    {
        $codes = Cache::remember('barcode-search:v2:'.md5($name), now()->addWeek(), function () use ($name) {
            $response = Http::connectTimeout(30)->timeout(45)
                ->withUserAgent('KaloMind/1.0')
                ->get('https://search.openfoodfacts.org/search', [
                    'q' => str_replace(' — ', ' ', $name),
                    'page_size' => 5,
                    'fields' => 'code',
                ]);

            return collect($response->successful() ? $response->json('hits') ?? [] : [])
                ->pluck('code')
                ->filter(fn ($code) => is_string($code) && preg_match('/^\d{8,14}$/', $code))
                ->values()
                ->all();
        });

        // the best matches don't always have a picture, so take the first one that does
        foreach ($codes as $code) {
            if ($image = $this->find($code)['image_url'] ?? null) {
                return $image;
            }
        }

        return null;
    }

    /**
     * Downloads an Open Food Facts product picture into the meal photos folder, so the meal
     * keeps its picture even if the product changes there. Returns the stored path or null.
     */
    public function storeImage(string $url, string $directory): ?string
    {
        if (! $this->imageUrl($url)) {
            return null;
        }

        try {
            $response = Http::connectTimeout(30)->timeout(45)->retry(2, 1000, throw: false)
                ->withUserAgent('KaloMind/1.0')
                ->get($url);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        $extension = match (strtolower(explode(';', (string) $response->header('Content-Type'))[0])) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };

        if (! $response->successful() || ! $extension || strlen($response->body()) > 5 * 1024 * 1024) {
            return null;
        }

        $path = "{$directory}/".Str::random(40).".{$extension}";
        Storage::disk('public')->put($path, $response->body());

        return $path;
    }

    /**
     * Only pictures hosted by Open Food Facts are accepted, since the URL later gets fetched server-side.
     */
    private function imageUrl(mixed $url): ?string
    {
        return is_string($url) && parse_url($url, PHP_URL_SCHEME) === 'https' && parse_url($url, PHP_URL_HOST) === self::IMAGE_HOST
            ? $url
            : null;
    }

    private function number(mixed $value): float
    {
        return is_numeric($value) ? max(0.0, (float) $value) : 0.0;
    }
}
