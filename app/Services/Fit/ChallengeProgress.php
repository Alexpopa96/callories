<?php

namespace App\Services\Fit;

use App\Models\Challenge;
use Carbon\CarbonImmutable;

class ChallengeProgress
{
    public function __construct(private DayStats $dayStats) {}

    /**
     * @return array{daysElapsed: int, daysLeft: int, totalDays: int, pctDays: int, startWeightKg: float, targetWeightKg: float, currentWeightKg: float, weightDeltaKg: float, pctWeight: ?int, avgCalories: int, avgProteinG: float, avgCarbsG: float, avgFatG: float, avgWaterMl: int, pctCalories: int, pctProtein: int, pctCarbs: int, pctFat: int, pctWater: int, adherencePct: int, lastWeighInDate: ?string}
     */
    public function forChallenge(Challenge $challenge): array
    {
        $today = CarbonImmutable::today();
        $from = CarbonImmutable::parse($challenge->started_on);
        $end = CarbonImmutable::parse($challenge->ends_on);
        $to = $today->lessThan($end) ? $today : $end;
        $totalDays = $from->diffInDays($end) + 1;
        $daysElapsed = min($totalDays, $from->diffInDays($today) + 1);

        $days = $this->dayStats->range($challenge->user, $from, $to);
        $loggedMeals = array_filter($days, fn (array $day) => $day['calories'] > 0);
        $loggedWater = array_filter($days, fn (array $day) => $day['water_ml'] > 0);

        $avg = fn (array $rows, string $key) => count($rows) > 0
            ? array_sum(array_column($rows, $key)) / count($rows)
            : 0.0;

        $avgCalories = (int) round($avg($loggedMeals, 'calories'));
        $avgProteinG = round($avg($loggedMeals, 'protein_g'), 1);
        $avgCarbsG = round($avg($loggedMeals, 'carbs_g'), 1);
        $avgFatG = round($avg($loggedMeals, 'fat_g'), 1);
        $avgWaterMl = (int) round($avg($loggedWater, 'water_ml'));

        $pctOf = fn (float $actual, float $target) => $target > 0
            ? (int) max(0, min(100, round($actual / $target * 100)))
            : 0;

        $adherencePct = $avgCalories > 0
            ? (int) max(0, round(100 - min(100, abs($avgCalories - $challenge->calorie_goal) / $challenge->calorie_goal * 100)))
            : 0;

        $lastWeighIn = $challenge->user->weightLogs()
            ->where('date', '>=', $from->toDateString())
            ->orderByDesc('date')
            ->first();

        $current = (float) ($lastWeighIn->weight_kg ?? $challenge->start_weight_kg);
        $weightDeltaKg = round($current - $challenge->start_weight_kg, 1);
        $totalTarget = $challenge->target_weight_kg - $challenge->start_weight_kg;
        $pctWeight = abs($totalTarget) > 0.01
            ? (int) max(0, min(100, round($weightDeltaKg / $totalTarget * 100)))
            : null;

        return [
            'daysElapsed' => $daysElapsed,
            'daysLeft' => max(0, $totalDays - $daysElapsed),
            'totalDays' => $totalDays,
            'pctDays' => (int) round($daysElapsed / $totalDays * 100),
            'startWeightKg' => (float) $challenge->start_weight_kg,
            'targetWeightKg' => (float) $challenge->target_weight_kg,
            'currentWeightKg' => $current,
            'weightDeltaKg' => $weightDeltaKg,
            'pctWeight' => $pctWeight,
            'avgCalories' => $avgCalories,
            'avgProteinG' => $avgProteinG,
            'avgCarbsG' => $avgCarbsG,
            'avgFatG' => $avgFatG,
            'avgWaterMl' => $avgWaterMl,
            'pctCalories' => $pctOf($avgCalories, $challenge->calorie_goal),
            'pctProtein' => $pctOf($avgProteinG, $challenge->protein_goal_g),
            'pctCarbs' => $pctOf($avgCarbsG, $challenge->carbs_goal_g),
            'pctFat' => $pctOf($avgFatG, $challenge->fat_goal_g),
            'pctWater' => $pctOf($avgWaterMl, $challenge->water_goal_ml),
            'adherencePct' => $adherencePct,
            'lastWeighInDate' => $lastWeighIn?->date,
        ];
    }
}
