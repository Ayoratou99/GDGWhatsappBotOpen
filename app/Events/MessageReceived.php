<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Diffusion synchrone, et non mise en file : le message entrant doit
 * apparaître avant que le bot n'appelle Meta, pas après. Un envoi sortant qui
 * traîne ne doit jamais retarder l'affichage de ce qui vient d'arriver.
 */
class MessageReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    /**
     * Le fil ouvert et la liste de gauche sont alimentés par deux canaux
     * distincts : la page n'est jamais rechargée.
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
