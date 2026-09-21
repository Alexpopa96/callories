<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Fit\BarcodeLookup;
use Illuminate\Http\JsonResponse;

class LookupBarcode extends Controller
{
    public function __invoke(string $code, BarcodeLookup $lookup): JsonResponse
    {
        if (! preg_match('/^\d{8,14}$/', $code)) {
            return response()->json(['message' => 'Codul de bare trebuie să aibă între 8 și 14 cifre.'], 422);
        }

        try {
            $product = $lookup->find($code);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Nu am putut contacta baza de produse. Încearcă din nou.'], 502);
        }

        if (! $product) {
            return response()->json(['message' => 'Nu am găsit produsul. Îl poți adăuga manual.'], 404);
        }

        return response()->json($product);
    }
}
