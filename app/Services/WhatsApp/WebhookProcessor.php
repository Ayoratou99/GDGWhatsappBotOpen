<?php

namespace App\Services\WhatsApp;

use App\Services\Bot\BotService;
use App\Services\ConversationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Traduit un payload Meta en données métier. Cette classe ne fait aucun appel
 * HTTP sortant et ne décide de rien : elle lit, elle délègue.
 */
class WebhookProcessor
{
    public function __construct(
        private ConversationService $conversations,
        private BotService $bot,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(array $payload): void
    {
        foreach (data_get($payload, 'entry', []) as $entry) {
            foreach (data_get($entry, 'changes', []) as $change) {
                $value = data_get($change, 'value', []);

                $this->handleMessages($value);
                $this->handleStatuses($value);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function handleMessages(array $value): void
    {
        $messages = data_get($value, 'messages', []);

        if (empty($messages)) {
            return;
        }

        // Meta fournit les noms de profil à part, indexés par wa_id.
        $profiles = collect(data_get($value, 'contacts', []))->keyBy('wa_id');

        foreach ($messages as $message) {
            $type = data_get($message, 'type');

            if ($type !== 'text') {
                // Image, audio, document, localisation : hors périmètre.
                // On log et on passe — surtout pas d'erreur 500.
                Log::info('Message non textuel ignoré.', [
                    'type' => $type,
                    'wam_id' => data_get($message, 'id'),
                ]);

                continue;
            }

            $waId = (string) data_get($message, 'from');

            $inbound = $this->conversations->recordInbound(
                waId: $waId,
                profileName: data_get($profiles->get($waId), 'profile.name'),
                wamId: (string) data_get($message, 'id'),
                body: (string) data_get($message, 'text.body', ''),
                sentAt: $this->timestamp(data_get($message, 'timestamp')),
            );

            // null = doublon déjà traité : le bot ne doit pas répondre deux fois.
            if ($inbound) {
                $this->bot->handle($inbound);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function handleStatuses(array $value): void
    {
        foreach (data_get($value, 'statuses', []) as $status) {
            $this->conversations->applyStatus(
                wamId: (string) data_get($status, 'id'),
                status: (string) data_get($status, 'status'),
                errorMessage: $this->errorMessage($status),
                occurredAt: $this->timestamp(data_get($status, 'timestamp')),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function errorMessage(array $status): ?string
    {
        $error = data_get($status, 'errors.0');

        if (! $error) {
            return null;
        }

        return trim(implode(' — ', array_filter([
            data_get($error, 'title'),
            data_get($error, 'error_data.details'),
        ]))) ?: null;
    }

    /**
     * Meta horodate en secondes Unix, transmises sous forme de chaîne.
     */
    private function timestamp(mixed $timestamp): ?Carbon
    {
        return is_numeric($timestamp) ? Carbon::createFromTimestamp((int) $timestamp) : null;
    }
}
