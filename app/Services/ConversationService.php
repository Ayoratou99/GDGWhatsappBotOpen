<?php

namespace App\Services;

use App\Events\MessageReceived;
use App\Events\MessageSent;
use App\Events\MessageStatusUpdated;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\WhatsApp\WhatsAppClient;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Toute écriture en base passe ici. Le client HTTP ne connaît pas nos modèles,
 * les modèles ne connaissent pas Meta : c'est ce service qui orchestre.
 */
class ConversationService
{
    public function __construct(private WhatsAppClient $client) {}

    /**
     * Enregistre un message entrant. Retourne null si le wam_id est déjà
     * connu : Meta réémet ses webhooks, et un doublon ne doit ni créer de
     * ligne, ni réveiller le bot une seconde fois.
     */
    public function recordInbound(
        string $waId,
        ?string $profileName,
        string $wamId,
        string $body,
        ?CarbonInterface $sentAt = null,
    ): ?Message {
        $sentAt = $sentAt ?? Carbon::now();

        $message = DB::transaction(function () use ($waId, $profileName, $wamId, $body, $sentAt) {
            $contact = Contact::updateOrCreate(
                ['wa_id' => $waId],
                // Ne jamais écraser un nom connu par un null de Meta.
                array_filter(['profile_name' => $profileName]),
            );

            $conversation = Conversation::firstOrCreate(['contact_id' => $contact->id]);

            $message = Message::updateOrCreate(
                ['wam_id' => $wamId],
                [
                    'conversation_id' => $conversation->id,
                    'direction' => Message::DIRECTION_INBOUND,
                    'author' => Message::AUTHOR_CONTACT,
                    'body' => $body,
                    'status' => Message::STATUS_DELIVERED,
                    'sent_at' => $sentAt,
                ],
            );

            if (! $message->wasRecentlyCreated) {
                return $message;
            }

            $conversation->forceFill([
                // Repart pour 24 h : c'est ce que le bandeau affiche.
                'last_inbound_at' => $sentAt,
                'last_message_at' => $sentAt,
                'unread_count' => $conversation->unread_count + 1,
            ])->save();

            $message->setRelation('conversation', $conversation->setRelation('contact', $contact));

            return $message;
        });

        if (! $message->wasRecentlyCreated) {
            Log::info('Webhook rejoué, message déjà connu.', ['wam_id' => $wamId]);

            return null;
        }

        MessageReceived::dispatch($message);

        return $message;
    }

    public function replyAsBot(Conversation $conversation, string $body): Message
    {
        return $this->sendOutbound($conversation, $body, Message::AUTHOR_BOT);
    }

    public function replyAsOperator(Conversation $conversation, string $body): Message
    {
        return $this->sendOutbound($conversation, $body, Message::AUTHOR_OPERATOR);
    }

    /**
     * Applique un statut reçu par webhook. Retourne null si le message est
     * inconnu ou si le statut n'apporte rien de neuf.
     */
    public function applyStatus(
        string $wamId,
        string $status,
        ?string $errorMessage = null,
        ?CarbonInterface $occurredAt = null,
    ): ?Message {
        $message = Message::where('wam_id', $wamId)->first();

        if (! $message) {
            Log::info('Statut reçu pour un message inconnu.', ['wam_id' => $wamId, 'status' => $status]);

            return null;
        }

        if (! array_key_exists($status, Message::STATUS_RANK)) {
            Log::info('Statut WhatsApp non géré.', ['wam_id' => $wamId, 'status' => $status]);

            return null;
        }

        // Les webhooks arrivent dans le désordre : jamais de rétrogradation.
        if (! Message::statusOutranks($status, $message->status)) {
            return null;
        }

        $message->forceFill(array_filter([
            'status' => $status,
            'error_message' => $errorMessage,
            'sent_at' => $message->sent_at ?? $occurredAt,
        ], fn ($value) => $value !== null))->save();

        MessageStatusUpdated::dispatch($message);

        return $message;
    }

    /**
     * L'opérateur a ouvert le fil : le compteur de non-lus retombe à zéro.
     */
    public function markAsRead(Conversation $conversation): void
    {
        if ($conversation->unread_count > 0) {
            $conversation->forceFill(['unread_count' => 0])->save();
        }
    }

    /**
     * Crée la ligne « pending », appelle Meta, puis retombe sur « sent » ou
     * « failed ». Le message existe en base même quand l'envoi échoue :
     * l'échec doit être visible à l'écran, pas seulement dans les logs.
     */
    private function sendOutbound(Conversation $conversation, string $body, string $author): Message
    {
        if (! $conversation->isWindowOpen()) {
            throw new RuntimeException('La fenêtre de 24 heures est fermée : Meta refuserait ce message.');
        }

        $conversation->loadMissing('contact');

        $message = $conversation->messages()->create([
            'direction' => Message::DIRECTION_OUTBOUND,
            'author' => $author,
            'body' => $body,
            'status' => Message::STATUS_PENDING,
        ]);

        try {
            $response = $this->client->sendText($conversation->contact->wa_id, $body);

            $message->forceFill([
                'wam_id' => data_get($response, 'messages.0.id'),
                'status' => Message::STATUS_SENT,
                'sent_at' => Carbon::now(),
            ])->save();
        } catch (Throwable $exception) {
            $message->forceFill([
                'status' => Message::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
            ])->save();
        }

        $conversation->forceFill(['last_message_at' => Carbon::now()])->save();

        $message->setRelation('conversation', $conversation);

        MessageSent::dispatch($message);

        return $message;
    }
}
