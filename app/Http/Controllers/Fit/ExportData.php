<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportData extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $user = $request->user();

        $data = [
            'exported_at' => now()->toIso8601String(),
            'account' => ['name' => $user->name, 'email' => $user->email, 'created_at' => $user->created_at?->toIso8601String()],
            'goals' => $user->goals(),
            'body' => [
                'sex' => $user->sex,
                'birth_date' => $user->birth_date?->toDateString(),
                'height_cm' => $user->height_cm,
                'activity_level' => $user->activity_level,
                'goal_type' => $user->goal_type,
            ],
            'meals' => $user->meals()->orderBy('eaten_on')->orderBy('id')->get()->map(fn ($meal) => [
                'date' => substr($meal->eaten_on, 0, 10),
                'title' => $meal->title,
                'calories' => $meal->calories,
                'protein_g' => $meal->protein_g,
                'carbs_g' => $meal->carbs_g,
                'fat_g' => $meal->fat_g,
                'fiber_g' => $meal->fiber_g,
                'items' => $meal->items,
                'notes' => $meal->notes,
            ])->all(),
            'daily_logs' => $user->dailyLogs()->orderBy('date')->get()->map(fn ($log) => [
                'date' => substr($log->date, 0, 10),
                'steps' => $log->steps,
                'water_ml' => $log->water_ml,
            ])->all(),
            'weight_logs' => $user->weightLogs()->orderBy('date')->get()->map(fn ($log) => [
                'date' => substr($log->date, 0, 10),
                'weight_kg' => $log->weight_kg,
            ])->all(),
            'favorite_foods' => $user->favoriteFoods()->orderBy('name')->get()->makeHidden(['id', 'user_id'])->toArray(),
        ];

        return response()->streamDownload(
            fn () => print json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'calorii-export-'.now()->toDateString().'.json',
            ['Content-Type' => 'application/json'],
        );
    }
}
