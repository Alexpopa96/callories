<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Fit\PushSender;
use App\Services\Fit\ReminderPlanner;
use Illuminate\Console\Command;

class SendFitReminders extends Command
{
    protected $signature = 'fit:send-reminders';

    protected $description = 'Send meal and water reminders to users who turned them on (run hourly).';

    public function handle(ReminderPlanner $planner, PushSender $sender): int
    {
        if (! $sender->configured()) {
            $this->warn('VAPID keys are missing; nothing was sent.');

            return self::SUCCESS;
        }

        $now = now();
        $sent = 0;

        User::query()
            ->where(fn ($query) => $query->where('remind_meals', true)->orWhere('remind_water', true))
            ->whereHas('pushSubscriptions')
            ->each(function (User $user) use ($planner, $sender, $now, &$sent) {
                foreach ($planner->messagesFor($user, $now) as $message) {
                    $sent += $sender->send($user, $message);
                }
            });

        $this->info("Sent {$sent} notification(s).");

        return self::SUCCESS;
    }
}
