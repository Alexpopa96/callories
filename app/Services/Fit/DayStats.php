<?php

namespace App\Services\Fit;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DayStats
{
    public static function resolveDate(mixed $value): CarbonImmutable
    {
        $today = CarbonImmutable::today();

        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $today;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return $today;
        }

        if ($date->format('Y-m-d') !== $value || $date->greaterThan($today)) {
            return $today;
        }

        return $date;
    }

    public static function label(CarbonImmutable $date): string
    {
        $text = $date->locale('ro')->isoFormat('dddd, D MMMM');

        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    /**
     * Per-day totals for the range, keyed by Y-m-d, newest day first.
     *
     * @return array<string, array{date: string, calories: int, protein_g: float, carbs_g: float, fat_g: float, fiber_g: float, meals: int, steps: int, water_ml: int, exercise_calories: int, sleep_minutes: int}>
     */
    public function range(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $bounds = [$from->toDateString(), $to->toDateString()];

        $meals = DB::table('meals')
            ->where('user_id', $user->id)
            ->whereBetween('eaten_on', $bounds)
            ->selectRaw('eaten_on, SUM(calories) as calories, SUM(protein_g) as protein_g, SUM(carbs_g) as carbs_g, SUM(fat_g) as fat_g, SUM(fiber_g) as fiber_g, COUNT(*) as meals')
            ->groupBy('eaten_on')
            ->get()
            ->keyBy(fn ($row) => substr($row->eaten_on, 0, 10));

        $logs = DB::table('daily_logs')
            ->where('user_id', $user->id)
            ->whereBetween('date', $bounds)
            ->get()
            ->keyBy(fn ($row) => substr($row->date, 0, 10));

        $days = [];

        for ($day = $to; $day->greaterThanOrEqualTo($from); $day = $day->subDay()) {
            $key = $day->toDateString();
            $meal = $meals->get($key);
            $log = $logs->get($key);

            $days[$key] = [
                'date' => $key,
                'calories' => (int) ($meal?->calories ?? 0),
                'protein_g' => round((float) ($meal?->protein_g ?? 0), 1),
                'carbs_g' => round((float) ($meal?->carbs_g ?? 0), 1),
                'fat_g' => round((float) ($meal?->fat_g ?? 0), 1),
                'fiber_g' => round((float) ($meal?->fiber_g ?? 0), 1),
                'meals' => (int) ($meal?->meals ?? 0),
                'steps' => (int) ($log?->steps ?? 0),
                'water_ml' => (int) ($log?->water_ml ?? 0),
                'exercise_calories' => (int) ($log?->exercise_calories ?? 0),
                'sleep_minutes' => (int) ($log?->sleep_minutes ?? 0),
            ];
        }

        return $days;
    }
}
