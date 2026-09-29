<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Friendship extends Model
{
    protected $fillable = ['user_id', 'friend_id', 'status', 'accepted_at'];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    /** The one who sent the request. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The one who received the request. */
    public function friend(): BelongsTo
    {
        return $this->belongsTo(User::class, 'friend_id');
    }

    public function scopeInvolving(Builder $query, User $user): void
    {
        $query->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('friend_id', $user->id));
    }

    public function scopeBetween(Builder $query, User $a, User $b): void
    {
        $query->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('user_id', $a->id)->where('friend_id', $b->id))
            ->orWhere(fn ($q) => $q->where('user_id', $b->id)->where('friend_id', $a->id)));
    }

    public function otherThan(User $user): User
    {
        return $this->user_id === $user->id ? $this->friend : $this->user;
    }
}
