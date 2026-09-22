<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Fit\AssistantException;
use App\Services\Fit\AssistantQuota;
use App\Services\Fit\DayStats;
use App\Services\Fit\NutritionAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AskAssistant extends Controller
{
    public function __invoke(Request $request, NutritionAssistant $assistant, AssistantQuota $quota): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:300'],
            'history' => ['array', 'max:8'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:1000'],
        ], [
            'message.required' => 'Scrie o întrebare.',
        ]);

        $user = $request->user();

        if ($quota->exhausted($user)) {
            return response()->json([
                'message' => "Ai pus deja {$quota->limit()} întrebări azi. Mai poți întreba mâine.",
                'questions_left' => 0,
            ], 429);
        }

        $quota->consume($user);

        $today = DayStats::resolveDate(null)->toDateString();
        $meals = $user->meals()->where('eaten_on', $today)->get();
        $goals = $user->goals();

        $context = [
            'obiective_zilnice' => $goals,
            'mancat_azi' => [
                'calorii' => (int) $meals->sum('calories'),
                'proteine_g' => round($meals->sum('protein_g'), 1),
                'carbohidrati_g' => round($meals->sum('carbs_g'), 1),
                'grasimi_g' => round($meals->sum('fat_g'), 1),
            ],
            'ramas_azi' => [
                'calorii' => $goals['calories'] - (int) $meals->sum('calories'),
                'proteine_g' => $goals['proteinG'] ? round($goals['proteinG'] - $meals->sum('protein_g'), 1) : null,
                'carbohidrati_g' => $goals['carbsG'] ? round($goals['carbsG'] - $meals->sum('carbs_g'), 1) : null,
                'grasimi_g' => $goals['fatG'] ? round($goals['fatG'] - $meals->sum('fat_g'), 1) : null,
            ],
            'alimente_favorite' => $user->favoriteFoods()->orderBy('name')->limit(20)->get(
                ['name', 'portion_grams', 'calories', 'protein_g', 'carbs_g', 'fat_g', 'fiber_g']
            )->toArray(),
        ];

        try {
            $result = $assistant->ask($context, $data['history'] ?? [], $data['message']);
        } catch (AssistantException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([...$result, 'questions_left' => $quota->remaining($user)]);
    }
}
