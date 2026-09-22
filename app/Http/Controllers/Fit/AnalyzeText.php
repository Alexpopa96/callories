<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Calories\FoodAnalysisException;
use App\Services\Calories\FoodTextAnalyzer;
use App\Services\Fit\TextQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyzeText extends Controller
{
    public function __invoke(Request $request, FoodTextAnalyzer $analyzer, TextQuota $quota): JsonResponse
    {
        $request->validate([
            'description' => ['required', 'string', 'max:500'],
        ], [
            'description.required' => 'Scrie ce ai mâncat.',
            'description.max' => 'Descrierea poate avea maxim 500 de caractere.',
        ]);

        $user = $request->user();

        if ($quota->exhausted($user)) {
            return response()->json([
                'message' => "Ai folosit cele {$quota->limit()} analize de azi. Poți adăuga masa manual sau încerca mâine.",
                'texts_left' => 0,
            ], 429);
        }

        $quota->consume($user);

        try {
            $result = $analyzer->analyze($request->string('description')->trim()->toString());
        } catch (FoodAnalysisException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([...$result, 'texts_left' => $quota->remaining($user)]);
    }
}
