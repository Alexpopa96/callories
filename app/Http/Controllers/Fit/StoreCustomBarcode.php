<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Models\CustomBarcode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StoreCustomBarcode extends Controller
{
    public function __invoke(string $code, Request $request): JsonResponse
    {
        if (! preg_match('/^\d{8,14}$/', $code)) {
            return response()->json(['message' => 'Codul de bare trebuie să aibă între 8 și 14 cifre.'], 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'portion_grams' => ['required', 'numeric', 'min:1', 'max:5000'],
            'calories' => ['required', 'numeric', 'min:0', 'max:9000'],
            'protein_g' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'carbs_g' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'fat_g' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'fiber_g' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        $product = CustomBarcode::updateOrCreate(['code' => $code], [
            'name' => $data['name'],
            'portion_grams' => (int) round($data['portion_grams']),
            'calories' => (int) round($data['calories']),
            'protein_g' => $data['protein_g'] ?? 0,
            'carbs_g' => $data['carbs_g'] ?? 0,
            'fat_g' => $data['fat_g'] ?? 0,
            'fiber_g' => $data['fiber_g'] ?? 0,
            'created_by' => $request->user()->id,
        ]);

        Cache::forget("barcode:{$code}");

        $factor = $product->portion_grams > 0 ? 100 / $product->portion_grams : 1;

        return response()->json([
            'name' => $product->name,
            'portion_grams' => (float) $product->portion_grams,
            'calories' => (float) $product->calories,
            'protein_g' => (float) $product->protein_g,
            'carbs_g' => (float) $product->carbs_g,
            'fat_g' => (float) $product->fat_g,
            'fiber_g' => (float) $product->fiber_g,
            'per100' => array_map(
                fn (float $value) => round($value * $factor, 1),
                ['calories' => $product->calories, 'protein_g' => $product->protein_g, 'carbs_g' => $product->carbs_g, 'fat_g' => $product->fat_g, 'fiber_g' => $product->fiber_g]
            ),
        ]);
    }
}
