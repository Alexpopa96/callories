<?php

namespace App\Services\Fit;

use App\Models\Challenge;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ReminderPlanner
{
    /**
     * Notifications a user should get at this hour (app timezone), based on what they logged today.
     *
     * @return list<array{title: string, body: string, url: string}>
     */
    public function messagesFor(User $user, CarbonInterface $now): array
    {
        $messages = [];
        $goals = $user->goals();
        $today = $now->toDateString();

        if ($user->remind_meals && in_array($now->hour, [13, 20], true)) {
            $meals = $user->meals()->where('eaten_on', $today);
            $calories = (int) (clone $meals)->sum('calories');

            if ($now->hour === 13 && $meals->count() === 0) {
                $messages[] = [
                    'title' => 'Ai mâncat azi?',
                    'body' => 'Nu ai adăugat nicio masă. Fotografiază prânzul în câteva secunde.',
                    'url' => '/scan',
                ];
            }

            if ($now->hour === 20 && $calories < $goals['calories'] * 0.5) {
                $messages[] = [
                    'title' => 'Mai ai loc pentru cină',
                    'body' => "Ai înregistrat {$calories} din {$goals['calories']} kcal azi.",
                    'url' => '/scan',
                ];
            }
        }

        $waterShare = [11 => 0.25, 15 => 0.6, 19 => 0.9];

        if ($user->remind_water && isset($waterShare[$now->hour])) {
            $drunk = (int) $user->dailyLogs()->where('date', $today)->value('water_ml');
            $expected = (int) round($goals['waterMl'] * $waterShare[$now->hour]);

            if ($drunk < $expected) {
                $messages[] = [
                    'title' => 'Bea un pahar cu apă',
                    'body' => 'Ai băut '.number_format($drunk / 1000, 1, ',', '').' L din '.number_format($goals['waterMl'] / 1000, 1, ',', '').' L.',
                    'url' => '/today',
                ];
            }
        }

        return $messages;
    }

    /**
     * A single notification when a just-saved meal pushes today's total across the calorie
     * goal or close to it (90%), or null when neither threshold was just crossed.
     *
     * @return array{title: string, body: string, url: string}|null
     */
    public function calorieLimitMessage(int $previousTotal, int $newTotal, int $goal): ?array
    {
        if ($goal <= 0) {
            return null;
        }

        if ($previousTotal < $goal && $newTotal >= $goal) {
            return [
                'title' => 'Ai depășit limita zilnică',
                'body' => "Ai ajuns la {$newTotal} din {$goal} kcal azi.",
                'url' => '/today',
            ];
        }

        $near = (int) round($goal * 0.9);

        if ($previousTotal < $near && $newTotal >= $near) {
            return [
                'title' => 'Te apropii de limita zilnică',
                'body' => "Ai ajuns la {$newTotal} din {$goal} kcal azi.",
                'url' => '/today',
            ];
        }

        return null;
    }

    /**
     * Daily check-in notifications for an active challenge: a nudge to weigh in, off-pace
     * alerts, and one-time milestone messages at 25/50/75% of the challenge's days.
     *
     * @param  array{daysElapsed: int, pctDays: int, avgCalories: int, lastWeighInDate: ?string}  $progress
     * @return list<array{title: string, body: string, url: string}>
     */
    public function challengeMessages(User $user, Challenge $challenge, array $progress, CarbonInterface $now): array
    {
        if (! $user->remind_challenge) {
            return [];
        }

        $messages = [];

        if ($now->hour === 9) {
            $daysSinceWeighIn = $progress['lastWeighInDate']
                ? CarbonImmutable::parse($progress['lastWeighInDate'])->diffInDays($now)
                : $progress['daysElapsed'];

            if ($daysSinceWeighIn >= 3) {
                $messages[] = [
                    'title' => 'Cântărește-te',
                    'body' => "Nu ai mai notat greutatea de {$daysSinceWeighIn} zile în provocarea ta.",
                    'url' => '/challenge',
                ];
            }

            foreach ([75, 50, 25] as $milestone) {
                if ($progress['pctDays'] >= $milestone && $challenge->notified_milestone_pct < $milestone) {
                    $messages[] = [
                        'title' => 'Progres în provocare',
                        'body' => "Ai parcurs {$milestone}% din provocare. Continuă așa!",
                        'url' => '/challenge',
                    ];
                    $challenge->update(['notified_milestone_pct' => $milestone]);
                    break;
                }
            }
        }

        if ($now->hour === 21 && $progress['avgCalories'] > 0 && $challenge->calorie_goal > 0) {
            $deviation = abs($progress['avgCalories'] - $challenge->calorie_goal) / $challenge->calorie_goal;

            if ($deviation > 0.2) {
                $over = $progress['avgCalories'] > $challenge->calorie_goal;
                $messages[] = [
                    'title' => $over ? 'Ești peste ritm' : 'Ești sub ritm',
                    'body' => "Media ta e {$progress['avgCalories']} kcal/zi față de ținta de {$challenge->calorie_goal} kcal.",
                    'url' => '/challenge',
                ];
            }
        }

        return $messages;
    }
}
