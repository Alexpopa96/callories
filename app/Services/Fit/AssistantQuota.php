<?php

namespace App\Services\Fit;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Daily cap on assistant questions per user: every question is a paid API call.
 */
class AssistantQuota
{
    public function limit(): int
    {
        return (int) config('services.anthropic.daily_assistant_limit');
    }

    public function exhausted(User $user): bool
    {
        return $this->limit() > 0 && RateLimiter::tooManyAttempts($this->key($user), $this->limit());
    }

    public function consume(User $user): void
    {
        RateLimiter::hit($this->key($user), 86400);
    }

    /** Questions left today, or null when there is no cap. */
    public function remaining(User $user): ?int
    {
        return $this->limit() > 0 ? max(0, $this->limit() - RateLimiter::attempts($this->key($user))) : null;
    }

    private function key(User $user): string
    {
        return "assistant:{$user->id}:".now()->toDateString();
    }
}
