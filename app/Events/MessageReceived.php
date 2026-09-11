<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    /**
     * Le fil ouvert et la liste de gauche sont alimentés par deux canaux
     * distincts : on ne recharge jamais la page pendant la démo.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->message->conversation_id),
            new PrivateChannel('conversations'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.received';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message->toPayload(),
            // Contient window_expires_at : le bandeau repart de zéro à
            // chaque message entrant.
            'conversation' => $this->message->conversation->fresh()->toSidebarPayload(),
        ];
    }
}
