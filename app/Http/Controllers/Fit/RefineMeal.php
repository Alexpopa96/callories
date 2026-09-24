<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Calories\FoodAnalysisException;
use App\Services\Calories\MealRefiner;
use App\Services\Fit\BarcodeLookup;
use App\Services\Fit\MealBuilder;
use App\Services\Fit\TextQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefineMeal extends Controller
{
    public function __invoke(Request $request, MealRefiner $refiner, TextQuota $quota, BarcodeLookup $barcodes): JsonResponse
    {
        $data = $request->validate([
            ...MealBuilder::itemRules(),
            'remark' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'remark.required' => 'Scrie ce nu e corect.',
            'remark.max' => 'Remarca poate avea maxim 500 de caractere.',
        ]);

        $user = $request->user();

        if ($quota->exhausted($user)) {
            return response()->json([
                'message' => "Ai folosit cele {$quota->limit()} analize de azi. Poți corecta porțiile manual sau încerca mâine.",
                'texts_left' => 0,
            ], 429);
        }

        $quota->consume($user);

        try {
            $result = $refiner->refine(
                MealBuilder::attributes($data['items'])['items'],
                trim($data['remark']),
                $data['notes'] ?? null,
            );
        } catch (FoodAnalysisException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $result['items'] = $this->keepProductPictures($data['items'], $result['items'] ?? [], $barcodes);

        return response()->json([...$result, 'texts_left' => $quota->remaining($user)]);
    }

    /**
     * Unchanged products keep their picture and barcode. A scanned product the remark turned into
     * another one (e.g. "it was the light version") gets the picture of the new name instead.
     *
     * @param  array<int, array<string, mixed>>  $before
     * @param  array<int, array<string, mixed>>  $after
     * @return array<int, array<string, mixed>>
     */
    private function keepProductPictures(array $before, array $after, BarcodeLookup $barcodes): array
    {
        $products = collect($before)->filter(fn (array $item) => ! empty($item['image_url']) || ! empty($item['barcode']));
        $names = collect($after)->pluck('name');
        $replaced = $products->reject(fn (array $item) => $names->contains($item['name']))->count();

        return collect($after)->map(function (array $item) use ($products, &$replaced, $barcodes) {
            $same = $products->firstWhere('name', $item['name']);

            if ($same) {
                return [...$item, 'image_url' => $same['image_url'] ?? null, 'barcode' => $same['barcode'] ?? null];
            }

            if ($replaced > 0) {
                $replaced--;

                try {
                    return [...$item, 'image_url' => $barcodes->imageForName($item['name'])];
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return $item;
        })->all();
    }
}
