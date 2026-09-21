<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Fit\ChallengeProgress;
use App\Services\Fit\PushSender;
use App\Services\Fit\ReminderPlanner;
use Illuminate\Console\Command;

class SendFitReminders extends Command
{
    protected $signature = 'fit:send-reminders';

    protected $description = 'Send meal and water reminders to users who turned them on (run hourly).';

    public function handle(ReminderPlanner $planner, PushSender $sender, ChallengeProgress $challengeProgress): int
    {
        if (! $sender->configured()) {
            $this->warn('VAPID keys are missing; nothing was sent.');

            return self::SUCCESS;
        }

        $now = now();
        $sent = 0;

        User::query()
            ->where(fn ($query) => $query->where('remind_meals', true)->orWhere('remind_water', true)->orWhere('remind_challenge', true))
            ->whereHas('pushSubscriptions')
            ->with('activeChallenge')
            ->each(function (User $user) use ($planner, $sender, $challengeProgress, $now, &$sent) {
                foreach ($planner->messagesFor($user, $now) as $message) {
                    $sent += $sender->send($user, $message);
                }

                $challenge = $user->activeChallenge;

                if (! $challenge) {
                    return;
                }

                if ($challenge->completeIfDue()) {
                    if ($user->remind_challenge) {
                        $sent += $sender->send($user, [
                            'title' => 'Provocare încheiată',
                            'body' => 'Felicitări! Ai încheiat provocarea. Vezi rezultatul.',
                            'url' => '/challenge',
                        ]);
                    }

                    return;
                }

                foreach ($planner->challengeMessages($user, $challenge, $challengeProgress->forChallenge($challenge), $now) as $message) {
                    $sent += $sender->send($user, $message);
                }
            });

        $this->info("Sent {$sent} notification(s).");

        return self::SUCCESS;
    }
}
