<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Seul point de sortie HTTP vers la Graph API. Cette classe ne connaît ni nos
 * modèles ni notre base : elle parle à Meta, rien de plus.
 */
class WhatsAppClient
{
    /**
     * Envoie un message texte et retourne la réponse Meta décodée.
     * La clé qui compte est messages.0.id : le wam_id auquel se rattacheront
     * tous les statuts qui arriveront ensuite.
     *
     * @return array<string, mixed>
     */
    public function sendText(string $to, string $body): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $body,
            ],
        ];

        try {
            $response = Http::withToken(config('whatsapp.token'))
                ->timeout(15)
                // throw: false — on veut lire le corps de l'erreur Meta,
                // qui est explicite, plutôt que de recevoir une exception nue.
                ->retry(2, 200, throw: false)
                ->post($this->endpoint(), $payload);
        } catch (ConnectionException $exception) {
            Log::error('WhatsApp injoignable.', [
                'to' => $to,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException('WhatsApp injoignable : '.$exception->getMessage(), 0, $exception);
        }

        if ($response->failed()) {
            // Le corps complet : les erreurs Graph sont explicites, et c'est
            // là que se lit la cause exacte d'un refus.
            Log::error('Envoi WhatsApp refusé par Meta.', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException($this->errorMessage($response->json(), $response->status()));
        }

        return $response->json() ?? [];
    }

    private function endpoint(): string
    {
        return sprintf(
            '%s/%s/%s/messages',
            rtrim((string) config('whatsapp.graph_url'), '/'),
            config('whatsapp.api_version'),
            config('whatsapp.phone_id'),
        );
    }

    /**
     * Message d'erreur court et lisible, affiché tel quel sous la bulle en
     * échec dans l'interface.
     *
     * @param  array<string, mixed>|null  $body
     */
    private function errorMessage(?array $body, int $status): string
    {
        $error = data_get($body, 'error', []);

        return trim(implode(' — ', array_filter([
            'Erreur Meta '.$status,
            data_get($error, 'message'),
            data_get($error, 'error_data.details'),
        ])));
    }
}
