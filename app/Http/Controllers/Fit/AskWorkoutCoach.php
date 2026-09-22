<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Fit\AssistantException;
use App\Services\Fit\WorkoutCoach;
use App\Services\Fit\WorkoutQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AskWorkoutCoach extends Controller
{
    public function __invoke(Request $request, WorkoutCoach $coach, WorkoutQuota $quota): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:300'],
            'history' => ['array', 'max:8'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:1000'],
        ], [
            'message.required' => 'Scrie ce vrei să faci azi.',
        ]);

        $user = $request->user();

        if ($quota->exhausted($user)) {
            return response()->json([
                'message' => "Ai pus deja {$quota->limit()} întrebări azi. Mai poți întreba mâine.",
                'questions_left' => 0,
            ], 429);
        }

        $quota->consume($user);

        $context = [
            'antrenamente_recente' => $user->workouts()->orderByDesc('date')->limit(5)->get(['date', 'title'])
                ->map(fn ($workout) => ['data' => $workout->date->toDateString(), 'titlu' => $workout->title])
                ->toArray(),
        ];

        try {
            $result = $coach->ask($context, $data['history'] ?? [], $data['message']);
        } catch (AssistantException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([...$result, 'questions_left' => $quota->remaining($user)]);
    }
}
