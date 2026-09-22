<?php

namespace App\Console\Commands;

use App\Models\GoalAdjustment;
use App\Models\User;
use App\Services\Fit\AdaptiveGoalCalculator;
use App\Services\Fit\PushSender;
use Illuminate\Console\Command;

class ReviewCalorieGoals extends Command
{
    protected $signature = 'fit:review-calorie-goals';

    protected $description = 'Compare logged intake against the real weight trend and suggest goal corrections (run weekly).';

    public function handle(AdaptiveGoalCalculator $calculator, PushSender $sender): int
    {
        $suggested = 0;

        User::query()
            ->whereNotNull('birth_date')
            ->whereNotNull('height_cm')
            ->whereIn('sex', ['m', 'f'])
            ->each(function (User $user) use ($calculator, $sender, &$suggested) {
                $recent = $user->goalAdjustments()->latest()->first();

                if ($recent && $recent->created_at->greaterThan(now()->subDays(6))) {
                    return;
                }

                $result = $calculator->evaluate($user);

                if (! $result) {
                    return;
                }

                GoalAdjustment::updateOrCreate(
                    ['user_id' => $user->id, 'status' => 'pending'],
                    [
                        'window_days' => $result['windowDays'],
                        'avg_intake_calories' => $result['avgIntake'],
                        'weight_change_kg' => $result['weightChangeKg'],
                        'implied_tdee' => $result['impliedTdee'],
                        'current_calories' => $result['currentCalories'],
                        'suggested_calories' => $result['suggestedCalories'],
                        'suggested_macros' => $result['macros'],
                        'message' => $result['message'],
                    ],
                );

                $sender->send($user, [
                    'title' => 'Obiectivul tău de calorii ar putea fi ajustat',
                    'body' => $result['message'],
                    'url' => '/me',
                ]);

                $suggested++;
            });

        $this->info("Suggested {$suggested} goal adjustment(s).");

        return self::SUCCESS;
    }
}
