<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Tells both people in a conversation that it changed. Carries ids only: the text is fetched
 * through the normal authorised route, so it never passes through the websocket server.
 */
class MessageSent implements ShouldBroadcastNow
{
    public function __construct(public Message $message) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        // the sender's own channel too, so their other devices update
        return [
            new PrivateChannel('App.Models.User.'.$this->message->recipient_id),
            new PrivateChannel('App.Models.User.'.$this->message->sender_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /** @return array{id: int, senderId: int, recipientId: int} */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'senderId' => $this->message->sender_id,
            'recipientId' => $this->message->recipient_id,
        ];
    }
}
