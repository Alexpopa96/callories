<?php

namespace App\Services\Fit;

use App\Models\User;
use Carbon\CarbonImmutable;

class ChallengePlanner
{
    public const GOALS = ['lose_weight', 'gain_weight', 'gain_muscle'];

    private const KCAL_PER_KG = 7700;

    private const MAX_WEEKLY_LOSS_KG = 1.0;

    private const MAX_WEEKLY_GAIN_KG = 0.5;

    private const MUSCLE_GAIN_SURPLUS = 275;

    /**
     * Mifflin-St Jeor based daily plan for a weight-loss/gain/muscle-gain challenge, with the
     * requested pace clamped to a safe range when it would otherwise be unrealistic.
     *
     * @return array{calories: int, proteinG: int, carbsG: int, fatG: int, waterMl: int, dailyCalorieDelta: int, adjusted: bool, explanation: string, bmr: int, tdee: int}
     */
    public function plan(User $user, float $startWeightKg, float $targetWeightKg, int $days, string $goal): array
    {
        $age = $user->birth_date->diffInYears(CarbonImmutable::today());
        $bmr = 10 * $startWeightKg + 6.25 * $user->height_cm - 5 * $age + ($user->sex === 'm' ? 5 : -161);
        $tdee = $bmr * (GoalCalculator::ACTIVITY[$user->activity_level] ?? GoalCalculator::ACTIVITY['light']);
        $floor = $user->sex === 'm' ? 1500 : 1200;
        $totalDeltaKg = $targetWeightKg - $startWeightKg;

        if ($goal === 'gain_muscle') {
            $delta = self::MUSCLE_GAIN_SURPLUS;
            $calories = (int) max($floor, round(($tdee + $delta) / 50) * 50);
            $adjusted = true;
            $explanation = 'Pentru masă musculară am folosit un surplus caloric moderat fix, indiferent de greutatea '
                .'țintă introdusă — creșterea musculară naturală este oricum lentă și nu depinde de câte zile alegi.';
        } else {
            $requestedDaily = ($totalDeltaKg * self::KCAL_PER_KG) / max(1, $days);

            if ($goal === 'lose_weight') {
                $capDaily = -(self::MAX_WEEKLY_LOSS_KG * self::KCAL_PER_KG) / 7;
                $delta = max($requestedDaily, $capDaily);
            } else {
                $capDaily = (self::MAX_WEEKLY_GAIN_KG * self::KCAL_PER_KG) / 7;
                $delta = min($requestedDaily, $capDaily);
            }

            $calories = (int) round(($tdee + $delta) / 50) * 50;
            $calories = (int) max($floor, $calories);
            $delta = $calories - (int) round($tdee);

            $adjusted = abs($delta - $requestedDaily) > 25;
            $explanation = $this->explain($goal, $adjusted, $delta, $days, $totalDeltaKg);
        }

        $proteinPerKg = match ($goal) {
            'lose_weight', 'gain_muscle' => 2.1,
            'gain_weight' => 1.7,
        };
        $proteinG = (int) round($startWeightKg * $proteinPerKg);
        $fatG = (int) round($calories * 0.25 / 9);
        $carbsG = (int) max(0, round(($calories - $proteinG * 4 - $fatG * 9) / 4));
        $waterMl = (int) min(4000, max(1500, round($startWeightKg * 35 / 100) * 100));

        return [
            'calories' => $calories,
            'proteinG' => $proteinG,
            'carbsG' => $carbsG,
            'fatG' => $fatG,
            'waterMl' => $waterMl,
            'dailyCalorieDelta' => $delta,
            'adjusted' => $adjusted,
            'explanation' => $explanation,
            'bmr' => (int) round($bmr),
            'tdee' => (int) round($tdee),
        ];
    }

    private function explain(string $goal, bool $adjusted, int $delta, int $days, float $totalDeltaKg): string
    {
        $verb = $goal === 'lose_weight' ? 'deficit' : 'surplus';
        $abs = abs($delta);

        if (! $adjusted) {
            return "Plan sigur: {$verb} de {$abs} kcal/zi pentru a ajunge la ".abs(round($totalDeltaKg, 1))." kg în {$days} zile.";
        }

        return "Ritmul cerut nu este sigur, așa că am plafonat la un {$verb} de {$abs} kcal/zi "
            .'(ritm sănătos, sub 1 kg/săptămână la slăbit sau 0,5 kg/săptămână la îngrășat). '
            .'Dacă ținta nu e atinsă până la sfârșitul celor '.$days.' zile, provocarea se încheie oricum la data stabilită — poți porni una nouă.';
    }
}
