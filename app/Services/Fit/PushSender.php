<?php

namespace App\Services\Fit;

use App\Models\User;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushSender
{
    public function configured(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    /**
     * Send one notification to every device of the user and forget the subscriptions the push service dropped.
     *
     * @param  array{title: string, body: string, url: string}  $message
     */
    public function send(User $user, array $message): int
    {
        if (! $this->configured()) {
            return 0;
        }

        $subscriptions = $user->pushSubscriptions()->get()->keyBy('endpoint');

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $webPush = new WebPush(['VAPID' => [
            'subject' => config('services.webpush.subject'),
            'publicKey' => config('services.webpush.public_key'),
            'privateKey' => config('services.webpush.private_key'),
        ]]);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                ]),
                json_encode($message),
            );
        }

        $sent = 0;

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
            } elseif ($report->isSubscriptionExpired()) {
                $subscriptions->get($report->getEndpoint())?->delete();
            }
        }

        return $sent;
    }
}
