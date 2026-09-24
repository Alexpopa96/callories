<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Calories\FoodAnalysisException;
use App\Services\Calories\MealRefiner;
use App\Services\Fit\MealBuilder;
use App\Services\Fit\TextQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefineMeal extends Controller
{
    public function __invoke(Request $request, MealRefiner $refiner, TextQuota $quota): JsonResponse
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

        return response()->json([...$result, 'texts_left' => $quota->remaining($user)]);
    }
}
