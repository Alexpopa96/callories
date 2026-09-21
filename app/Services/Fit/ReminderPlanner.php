<?php

namespace App\Services\Fit;

use App\Models\User;
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
}
