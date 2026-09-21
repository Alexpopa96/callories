<?php

namespace App\Services\Fit;

use App\Models\User;
use Carbon\CarbonImmutable;

class GoalCalculator
{
    public const ACTIVITY = [
        'sedentary' => 1.2,
        'light' => 1.375,
        'moderate' => 1.55,
        'active' => 1.725,
    ];

    public const GOALS = ['lose', 'maintain', 'gain'];

    /**
     * Mifflin-St Jeor estimate of daily targets, or null while the profile is incomplete.
     *
     * @return array{calories: int, proteinG: int, carbsG: int, fatG: int, waterMl: int, bmr: int, tdee: int}|null
     */
    public function suggest(User $user, ?float $weightKg, ?CarbonImmutable $today = null): ?array
    {
        if (! $weightKg || ! $user->birth_date || ! $user->height_cm || ! in_array($user->sex, ['m', 'f'], true)) {
            return null;
        }

        $age = $user->birth_date->diffInYears($today ?? CarbonImmutable::today());
        $bmr = 10 * $weightKg + 6.25 * $user->height_cm - 5 * $age + ($user->sex === 'm' ? 5 : -161);
        $tdee = $bmr * (self::ACTIVITY[$user->activity_level] ?? self::ACTIVITY['light']);

        $calories = match ($user->goal_type) {
            'lose' => $tdee - 500,
            'gain' => $tdee + 300,
            default => $tdee,
        };
        $calories = (int) max($user->sex === 'm' ? 1500 : 1200, round($calories / 50) * 50);

        $proteinG = (int) round($weightKg * match ($user->goal_type) {
            'lose' => 2.0,
            'gain' => 1.8,
            default => 1.6,
        });
        $fatG = (int) round($calories * 0.25 / 9);
        $carbsG = (int) max(0, round(($calories - $proteinG * 4 - $fatG * 9) / 4));

        return [
            'calories' => $calories,
            'proteinG' => $proteinG,
            'carbsG' => $carbsG,
            'fatG' => $fatG,
            'waterMl' => (int) min(4000, max(1500, round($weightKg * 35 / 100) * 100)),
            'bmr' => (int) round($bmr),
            'tdee' => (int) round($tdee),
        ];
    }
}
