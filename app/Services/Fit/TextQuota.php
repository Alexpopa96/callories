<?php

namespace App\Services\Fit;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Daily cap on free-text meal analyses per user: every analysis is a paid API call.
 * Kept separate from ScanQuota since text calls are much cheaper than photo calls.
 */
class TextQuota
{
    public function limit(): int
    {
        return (int) config('services.anthropic.daily_text_limit');
    }

    public function exhausted(User $user): bool
    {
        return $this->limit() > 0 && RateLimiter::tooManyAttempts($this->key($user), $this->limit());
    }

    public function consume(User $user): void
    {
        RateLimiter::hit($this->key($user), 86400);
    }

    /** Analyses left today, or null when there is no cap. */
    public function remaining(User $user): ?int
    {
        return $this->limit() > 0 ? max(0, $this->limit() - RateLimiter::attempts($this->key($user))) : null;
    }

    private function key(User $user): string
    {
        return "texts:{$user->id}:".now()->toDateString();
    }
}
