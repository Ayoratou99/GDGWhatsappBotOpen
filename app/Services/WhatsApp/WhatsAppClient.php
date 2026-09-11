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
                ->post($this->url(config('whatsapp.phone_id').'/messages'), $payload);
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

    /**
     * Envoie un modèle approuvé. C'est le seul type de message que Meta
     * accepte hors de la fenêtre de 24 h — donc le seul moyen d'ouvrir une
     * conversation avec un contact qui n'a jamais écrit.
     *
     * @return array<string, mixed>
     */
    public function sendTemplate(string $to, string $template, string $language): array
    {
        $response = $this->post(config('whatsapp.phone_id').'/messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $language],
            ],
        ]);

        if (! $response['ok']) {
            Log::error('Invitation WhatsApp refusée par Meta.', [
                'to' => $to,
                'template' => $template,
                'error' => $response['error'],
            ]);

            throw new RuntimeException($response['error']);
        }

        return $response['data'];
    }

    /**
     * Lecture simple sur la Graph API. Contrairement à sendText, cette méthode
     * ne lève pas : l'appelant vérifie l'état de la liaison, un échec est une
     * réponse comme une autre.
     *
     * @param  array<string, mixed>  $query
     * @return array{ok: bool, data?: array<string, mixed>, error?: string}
     */
    public function get(string $path, array $query = []): array
    {
        try {
            $response = Http::withToken(config('whatsapp.token'))
                ->timeout(10)
                ->get($this->url($path), $query);
        } catch (ConnectionException $exception) {
            return ['ok' => false, 'error' => 'Graph API injoignable : '.$exception->getMessage()];
        }

        if ($response->failed()) {
            return ['ok' => false, 'error' => $this->errorMessage($response->json(), $response->status())];
        }

        return ['ok' => true, 'data' => $response->json() ?? []];
    }

    /**
     * Écriture simple sur la Graph API, même contrat que get() : les échecs
     * sont des valeurs de retour, pas des exceptions.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, data?: array<string, mixed>, error?: string}
     */
    public function post(string $path, array $payload = []): array
    {
        try {
            $response = Http::withToken(config('whatsapp.token'))
                ->timeout(15)
                ->post($this->url($path), $payload);
        } catch (ConnectionException $exception) {
            return ['ok' => false, 'error' => 'Graph API injoignable : '.$exception->getMessage()];
        }

        if ($response->failed()) {
            return ['ok' => false, 'error' => $this->errorMessage($response->json(), $response->status())];
        }

        return ['ok' => true, 'data' => $response->json() ?? []];
    }

    private function url(string $path): string
    {
        return sprintf(
            '%s/%s/%s',
            rtrim((string) config('whatsapp.graph_url'), '/'),
            config('whatsapp.api_version'),
            ltrim($path, '/'),
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
