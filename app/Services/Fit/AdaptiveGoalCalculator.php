<?php

namespace App\Services\Fit;

use App\Models\User;
use Carbon\CarbonImmutable;

class AdaptiveGoalCalculator
{
    private const WINDOW_DAYS = 14;

    private const MIN_WEIGHT_LOGS = 5;

    private const MIN_MEAL_DAYS = 10;

    private const KCAL_PER_KG = 7700;

    private const THRESHOLD_KCAL = 150;

    private const STABLE_KG = 0.2;

    public function __construct(
        private GoalCalculator $goalCalculator,
        private DayStats $dayStats,
    ) {}

    /**
     * Compare logged intake against the actual weight trend over the trailing window and, when
     * they diverge meaningfully from the current goal, return a suggested correction. Returns
     * null when there isn't enough data yet, or when the divergence is too small to matter.
     *
     * @return array{windowDays: int, avgIntake: int, weightChangeKg: float, impliedTdee: int, currentCalories: int, suggestedCalories: int, macros: array, message: string}|null
     */
    public function evaluate(User $user, ?CarbonImmutable $today = null): ?array
    {
        $weightKg = $user->latestWeight()?->weight_kg;

        if (! $this->goalCalculator->hasCompleteProfile($user, $weightKg)) {
            return null;
        }

        $today = $today ?? CarbonImmutable::today();
        $from = $today->subDays(self::WINDOW_DAYS - 1);

        $weights = $user->weightLogs()
            ->whereBetween('date', [$from->toDateString(), $today->toDateString()])
            ->orderBy('date')
            ->get(['date', 'weight_kg']);

        if ($weights->count() < self::MIN_WEIGHT_LOGS) {
            return null;
        }

        $days = $this->dayStats->range($user, $from, $today);
        $loggedDays = array_filter($days, fn (array $day) => $day['meals'] > 0);

        if (count($loggedDays) < self::MIN_MEAL_DAYS) {
            return null;
        }

        $avgIntake = array_sum(array_column($loggedDays, 'calories')) / count($loggedDays);

        $points = $weights->map(fn ($log) => [
            $from->diffInDays(CarbonImmutable::parse($log->date)),
            (float) $log->weight_kg,
        ])->all();

        $slopePerDay = $this->linearSlope($points);
        $weightChangeKg = round($slopePerDay * self::WINDOW_DAYS, 2);
        $impliedTdee = $avgIntake - ($slopePerDay * self::KCAL_PER_KG);

        $targets = $this->goalCalculator->deriveTargets($user, $impliedTdee, $weightKg);
        $currentCalories = (int) $user->calorie_goal;

        if (abs($targets['calories'] - $currentCalories) < self::THRESHOLD_KCAL) {
            return null;
        }

        return [
            'windowDays' => self::WINDOW_DAYS,
            'avgIntake' => (int) round($avgIntake),
            'weightChangeKg' => $weightChangeKg,
            'impliedTdee' => (int) round($impliedTdee),
            'currentCalories' => $currentCalories,
            'suggestedCalories' => $targets['calories'],
            'macros' => $targets,
            'message' => $this->buildMessage(
                self::WINDOW_DAYS,
                (int) round($avgIntake),
                $weightChangeKg,
                $targets['calories'],
                $currentCalories,
            ),
        ];
    }

    private function buildMessage(int $windowDays, int $avgIntake, float $weightChangeKg, int $suggestedCalories, int $currentCalories): string
    {
        $absChange = number_format(abs($weightChangeKg), 1, ',', '.');

        $trend = match (true) {
            $weightChangeKg <= -self::STABLE_KG => "ai slăbit doar {$absChange} kg",
            $weightChangeKg >= self::STABLE_KG => "ai luat {$absChange} kg",
            default => 'greutatea a rămas aproape neschimbată',
        };

        $direction = $suggestedCalories > $currentCalories
            ? 'organismul tău arde mai multe calorii decât am estimat'
            : 'organismul tău arde mai puține calorii decât am estimat';

        $action = $suggestedCalories > $currentCalories ? 'urcăm' : 'coborâm';

        return "În ultimele {$windowDays} zile ai mâncat în medie {$avgIntake} kcal și {$trend}. Se pare că {$direction}. Vrei să {$action} obiectivul la {$suggestedCalories} kcal?";
    }

    /**
     * Least-squares slope (kg per day) through the (dayIndex, weightKg) points.
     *
     * @param  list<array{0: int, 1: float}>  $points
     */
    private function linearSlope(array $points): float
    {
        $n = count($points);

        if ($n < 2) {
            return 0.0;
        }

        $sumX = $sumY = $sumXY = $sumXX = 0.0;

        foreach ($points as [$x, $y]) {
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumXX += $x * $x;
        }

        $denominator = $n * $sumXX - $sumX ** 2;

        if (abs($denominator) < 1e-9) {
            return 0.0;
        }

        return ($n * $sumXY - $sumX * $sumY) / $denominator;
    }
}
