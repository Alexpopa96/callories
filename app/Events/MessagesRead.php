<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Turns the sender's ticks blue as soon as the other person opens the conversation. */
class MessagesRead implements ShouldBroadcastNow
{
    public function __construct(public int $readerId, public int $senderId, public int $upTo) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->senderId),
            new PrivateChannel('App.Models.User.'.$this->readerId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'messages.read';
    }

    /** @return array{readerId: int, senderId: int, upTo: int} */
    public function broadcastWith(): array
    {
        return ['readerId' => $this->readerId, 'senderId' => $this->senderId, 'upTo' => $this->upTo];
    }
}
