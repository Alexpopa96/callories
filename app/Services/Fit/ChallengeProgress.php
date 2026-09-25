<?php

namespace App\Services\Fit;

use App\Models\Challenge;
use Carbon\CarbonImmutable;

class ChallengeProgress
{
    public function __construct(private DayStats $dayStats) {}

    /**
     * The given day clamped to the part of the challenge that has already happened.
     */
    public function clampDate(Challenge $challenge, CarbonImmutable $date): CarbonImmutable
    {
        $from = CarbonImmutable::parse($challenge->started_on);
        $end = CarbonImmutable::parse($challenge->ends_on);
        $today = CarbonImmutable::today();
        $last = $today->lessThan($end) ? $today : $end;

        return $date->lessThan($from) ? $from : ($date->greaterThan($last) ? $last : $date);
    }

    /**
     * Totals for a single day of the challenge, measured against the challenge targets.
     *
     * @return array{date: string, dayNumber: int, calories: int, proteinG: float, carbsG: float, fatG: float, waterMl: int, exerciseCalories: int, calorieBudget: int, meals: int, pctCalories: int, pctProtein: int, pctCarbs: int, pctFat: int, pctWater: int, prev: ?string, next: ?string, verdict: array{rating: string, reasons: list<array{ok: bool, text: string}>}}
     */
    public function forDay(Challenge $challenge, CarbonImmutable $date): array
    {
        $date = $this->clampDate($challenge, $date);
        $from = CarbonImmutable::parse($challenge->started_on);
        $day = $this->dayStats->range($challenge->user, $date, $date)[$date->toDateString()];

        // calories burned through exercise raise that day's budget, same as on the home screen
        $calorieBudget = (int) $challenge->calorie_goal + $day['exercise_calories'];

        $pctOf = fn (float $actual, float $target) => $target > 0
            ? (int) max(0, min(100, round($actual / $target * 100)))
            : 0;

        $prev = $date->subDay();
        $next = $date->addDay();

        return [
            'date' => $date->toDateString(),
            'dayNumber' => $from->diffInDays($date) + 1,
            'calories' => $day['calories'],
            'proteinG' => $day['protein_g'],
            'carbsG' => $day['carbs_g'],
            'fatG' => $day['fat_g'],
            'waterMl' => $day['water_ml'],
            'exerciseCalories' => $day['exercise_calories'],
            'calorieBudget' => $calorieBudget,
            'meals' => $day['meals'],
            'pctCalories' => $pctOf($day['calories'], $calorieBudget),
            'pctProtein' => $pctOf($day['protein_g'], $challenge->protein_goal_g),
            'pctCarbs' => $pctOf($day['carbs_g'], $challenge->carbs_goal_g),
            'pctFat' => $pctOf($day['fat_g'], $challenge->fat_goal_g),
            'pctWater' => $pctOf($day['water_ml'], $challenge->water_goal_ml),
            'prev' => $prev->greaterThanOrEqualTo($from) ? $prev->toDateString() : null,
            'next' => $this->clampDate($challenge, $next)->equalTo($next) ? $next->toDateString() : null,
            'verdict' => $this->verdict($challenge, $day, $calorieBudget, $date->isToday()),
        ];
    }

    /**
     * How the day went: calories count double, protein and water once each.
     * Today is still running, so it only gets a verdict once the calorie budget is blown.
     *
     * @param  array{calories: int, protein_g: float, water_ml: int, meals: int}  $day
     * @return array{rating: string, reasons: list<array{ok: bool, text: string}>}
     */
    private function verdict(Challenge $challenge, array $day, int $calorieBudget, bool $isToday): array
    {
        if ($day['meals'] === 0) {
            return ['rating' => $isToday ? 'pending' : 'empty', 'reasons' => []];
        }

        $deviation = $calorieBudget > 0 ? ($day['calories'] - $calorieBudget) / $calorieBudget * 100 : 0;
        $proteinPct = $challenge->protein_goal_g > 0 ? $day['protein_g'] / $challenge->protein_goal_g * 100 : 100;
        $waterPct = $challenge->water_goal_ml > 0 ? $day['water_ml'] / $challenge->water_goal_ml * 100 : 100;

        $calorieScore = abs($deviation) <= 10 ? 2 : (abs($deviation) <= 20 ? 1 : 0);
        $proteinScore = $proteinPct >= 80 ? 2 : ($proteinPct >= 60 ? 1 : 0);
        $waterScore = $waterPct >= 80 ? 2 : ($waterPct >= 50 ? 1 : 0);

        $reasons = [
            ['ok' => $calorieScore === 2, 'text' => match (true) {
                $calorieScore === 2 => 'Calorii în țintă',
                $deviation > 0 => 'Calorii cu '.round($deviation).'% peste buget',
                default => 'Calorii cu '.round(abs($deviation)).'% sub buget',
            }],
            ['ok' => $proteinScore === 2, 'text' => $proteinScore === 2 ? 'Proteine atinse' : 'Proteine '.round($proteinPct).'% din țintă'],
            ['ok' => $waterScore === 2, 'text' => match (true) {
                $waterScore === 2 => 'Apă suficientă',
                $day['water_ml'] === 0 => 'Apă nenotată',
                default => 'Apă '.round($waterPct).'% din țintă',
            }],
        ];

        if ($isToday && $deviation <= 10) {
            return ['rating' => 'pending', 'reasons' => $reasons];
        }

        $score = $calorieScore * 2 + $proteinScore + $waterScore;

        return [
            'rating' => $score >= 6 ? 'good' : ($score >= 4 ? 'ok' : 'bad'),
            'reasons' => $reasons,
        ];
    }

    /**
     * @return array{daysElapsed: int, daysLeft: int, totalDays: int, pctDays: int, startWeightKg: float, targetWeightKg: float, currentWeightKg: float, weightDeltaKg: float, pctWeight: ?int, avgCalories: int, avgProteinG: float, avgCarbsG: float, avgFatG: float, avgWaterMl: int, avgCalorieBudget: int, pctCalories: int, pctProtein: int, pctCarbs: int, pctFat: int, pctWater: int, adherencePct: int, lastWeighInDate: ?string}
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

        // calories burned through exercise raise that day's budget, same as on the home screen
        $avgCalorieBudget = count($loggedMeals) > 0
            ? (int) round($challenge->calorie_goal + $avg($loggedMeals, 'exercise_calories'))
            : (int) $challenge->calorie_goal;

        $pctOf = fn (float $actual, float $target) => $target > 0
            ? (int) max(0, min(100, round($actual / $target * 100)))
            : 0;

        $adherencePct = $avgCalories > 0
            ? (int) max(0, round(100 - min(100, abs($avgCalories - $avgCalorieBudget) / $avgCalorieBudget * 100)))
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
            'avgCalorieBudget' => $avgCalorieBudget,
            'pctCalories' => $pctOf($avgCalories, $avgCalorieBudget),
            'pctProtein' => $pctOf($avgProteinG, $challenge->protein_goal_g),
            'pctCarbs' => $pctOf($avgCarbsG, $challenge->carbs_goal_g),
            'pctFat' => $pctOf($avgFatG, $challenge->fat_goal_g),
            'pctWater' => $pctOf($avgWaterMl, $challenge->water_goal_ml),
            'adherencePct' => $adherencePct,
            'lastWeighInDate' => $lastWeighIn?->date,
        ];
    }
}
