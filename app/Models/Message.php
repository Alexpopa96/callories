<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = ['sender_id', 'recipient_id', 'client_id', 'body', 'read_at'];

    protected function casts(): array
    {
        return [
            // stored encrypted with APP_KEY: a database dump or backup shows no conversations
            'body' => 'encrypted',
            'read_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /** Both directions of the conversation between two users. */
    public function scopeBetween(Builder $query, User $a, User $b): void
    {
        $query->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('sender_id', $a->id)->where('recipient_id', $b->id))
            ->orWhere(fn ($q) => $q->where('sender_id', $b->id)->where('recipient_id', $a->id)));
    }

    /** @return array{id: int, clientId: ?string, body: string, mine: bool, read: bool, time: string, day: string} */
    public function toChatArray(User $viewer): array
    {
        return [
            'id' => $this->id,
            // only the sender needs it, to match a send whose response got lost
            'clientId' => $this->sender_id === $viewer->id ? $this->client_id : null,
            'body' => $this->body,
            'mine' => $this->sender_id === $viewer->id,
            'read' => $this->read_at !== null,
            'time' => $this->created_at->format('H:i'),
            'day' => $this->created_at->toDateString(),
        ];
    }
}
