<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * État de la liaison avec Meta. Chaque vérification en échec porte la
 * correction à appliquer : une configuration cassée doit se voir avant le
 * premier message, pas au moment où il n'arrive pas.
 */
class ConnectionChecker
{
    private const CACHE_KEY = 'whatsapp.connection-status';

    private const TTL = 60;

    public function __construct(private WhatsAppClient $client) {}

    /**
     * @return array<string, mixed>
     */
    public function check(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::TTL, fn () => $this->run());
    }

    /**
     * @return array<string, mixed>
     */
    private function run(): array
    {
        $configuration = $this->configuration();

        // Inutile d'interroger Meta avec des identifiants absents : la réponse
        // serait un message d'erreur qui masquerait la vraie cause.
        $checks = $configuration['status'] === 'error'
            ? [$configuration]
            : [$configuration, $this->phoneNumber(), $this->subscription()];

        $failed = collect($checks)->contains(fn (array $check) => $check['status'] === 'error');

        return [
            'status' => $failed ? 'error' : 'ok',
            'checked_at' => Carbon::now()->format('H:i:s'),
            'checks' => $checks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function configuration(): array
    {
        $missing = collect([
            'WHATSAPP_TOKEN' => config('whatsapp.token'),
            'WHATSAPP_WABA_ID' => config('whatsapp.waba_id'),
            'WHATSAPP_PHONE_ID' => config('whatsapp.phone_id'),
            'WHATSAPP_VERIFY_TOKEN' => config('whatsapp.verify_token'),
            'WHATSAPP_APP_SECRET' => config('whatsapp.app_secret'),
        ])->filter(fn ($value) => blank($value))->keys();

        if ($missing->isNotEmpty()) {
            return [
                'label' => 'Configuration',
                'status' => 'error',
                'detail' => 'Variables absentes : '.$missing->implode(', ').'.',
                'advice' => 'Renseignez ces clés dans .env, puis relancez la pile avec « docker compose up -d ».',
            ];
        }

        return [
            'label' => 'Configuration',
            'status' => 'ok',
            'detail' => 'Les cinq identifiants Meta sont renseignés.',
            'advice' => null,
        ];
    }

    /**
     * Valide d'un même coup le jeton et l'identifiant du numéro : un jeton
     * expiré se manifeste ici, avant qu'un envoi n'échoue.
     *
     * @return array<string, mixed>
     */
    private function phoneNumber(): array
    {
        $response = $this->client->get((string) config('whatsapp.phone_id'), [
            'fields' => 'display_phone_number,verified_name,quality_rating',
        ]);

        if (! $response['ok']) {
            return [
                'label' => 'Numéro et jeton',
                'status' => 'error',
                'detail' => $response['error'],
                'advice' => 'Vérifiez WHATSAPP_TOKEN et WHATSAPP_PHONE_ID. Un jeton temporaire expire au bout de 24 h : générez un jeton de System User pour une installation durable.',
            ];
        }

        return [
            'label' => 'Numéro et jeton',
            'status' => 'ok',
            'detail' => sprintf(
                '%s — +%s, qualité %s.',
                data_get($response['data'], 'verified_name', 'Numéro connecté'),
                ltrim((string) data_get($response['data'], 'display_phone_number', '—'), '+'),
                strtolower((string) data_get($response['data'], 'quality_rating', 'inconnue')),
            ),
            'advice' => null,
        ];
    }

    /**
     * Sans application abonnée au compte WhatsApp Business, aucun webhook
     * n'est jamais émis — et rien dans l'interface ne le laisserait deviner.
     *
     * @return array<string, mixed>
     */
    private function subscription(): array
    {
        $response = $this->client->get(config('whatsapp.waba_id').'/subscribed_apps');

        if (! $response['ok']) {
            return [
                'label' => 'Abonnement aux webhooks',
                'status' => 'error',
                'detail' => $response['error'],
                'advice' => 'Vérifiez WHATSAPP_WABA_ID, et que le jeton porte bien la permission whatsapp_business_management.',
            ];
        }

        $apps = data_get($response['data'], 'data', []);

        if (empty($apps)) {
            return [
                'label' => 'Abonnement aux webhooks',
                'status' => 'error',
                'detail' => "Aucune application n'est abonnée à ce compte WhatsApp Business.",
                'advice' => 'Aucun message entrant ne peut arriver. Abonnez l\'application (POST sur /{WABA_ID}/subscribed_apps), puis cochez le champ « messages » dans la configuration du webhook.',
            ];
        }

        $names = collect($apps)
            ->map(fn ($app) => data_get($app, 'whatsapp_business_api_data.name'))
            ->filter()
            ->implode(', ');

        return [
            'label' => 'Abonnement aux webhooks',
            'status' => 'ok',
            'detail' => $names !== '' ? 'Application abonnée : '.$names.'.' : 'Une application est abonnée.',
            'advice' => null,
        ];
    }
}
