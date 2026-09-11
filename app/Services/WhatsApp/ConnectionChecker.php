<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * État de la liaison avec Meta, et réparation des deux pannes qui se corrigent
 * depuis la console : un numéro non enregistré, une application non abonnée.
 *
 * Chaque vérification en échec porte la correction à appliquer. Une liaison
 * cassée doit se voir avant le premier message, pas au moment où il manque.
 */
class ConnectionChecker
{
    private const CACHE_KEY = 'whatsapp.connection-status';

    private const TTL = 60;

    private const DOC_REGISTRATION = 'https://developers.facebook.com/docs/whatsapp/cloud-api/reference/registration';

    private const DOC_WEBHOOKS = 'https://developers.facebook.com/docs/whatsapp/cloud-api/guides/set-up-webhooks';

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
     * Enregistre le numéro auprès de la Cloud API. Le PIN devient celui de la
     * vérification en deux étapes du numéro : il doit être conservé.
     *
     * @return array{ok: bool, message: string}
     */
    public function registerPhoneNumber(string $pin): array
    {
        $response = $this->client->post(config('whatsapp.phone_id').'/register', [
            'messaging_product' => 'whatsapp',
            'pin' => $pin,
        ]);

        Cache::forget(self::CACHE_KEY);

        return $response['ok']
            ? ['ok' => true, 'message' => 'Numéro enregistré auprès de la Cloud API. Conservez ce PIN.']
            : ['ok' => false, 'message' => $response['error']];
    }

    /**
     * Abonne l'application aux webhooks du compte WhatsApp Business.
     *
     * @return array{ok: bool, message: string}
     */
    public function subscribeApp(): array
    {
        $response = $this->client->post(config('whatsapp.waba_id').'/subscribed_apps');

        Cache::forget(self::CACHE_KEY);

        return $response['ok']
            ? ['ok' => true, 'message' => 'Application abonnée aux webhooks du compte.']
            : ['ok' => false, 'message' => $response['error']];
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
            return $this->check_(
                'Configuration',
                'error',
                'Variables absentes : '.$missing->implode(', ').'.',
                'Renseignez ces clés dans .env, puis relancez la pile avec « docker compose up -d ».',
            );
        }

        return $this->check_('Configuration', 'ok', 'Les cinq identifiants Meta sont renseignés.');
    }

    /**
     * Valide d'un même coup le jeton, l'identifiant du numéro et son
     * enregistrement : un jeton expiré ou un numéro non enregistré se
     * manifestent ici, avant qu'un envoi n'échoue.
     *
     * @return array<string, mixed>
     */
    private function phoneNumber(): array
    {
        $response = $this->client->get((string) config('whatsapp.phone_id'), [
            'fields' => 'display_phone_number,verified_name,quality_rating,platform_type,status',
        ]);

        if (! $response['ok']) {
            return $this->check_(
                'Numéro et jeton',
                'error',
                $response['error'],
                'Vérifiez WHATSAPP_TOKEN et WHATSAPP_PHONE_ID. Un jeton temporaire expire au bout de 24 h : générez un jeton de System User pour une installation durable.',
            );
        }

        // Un numéro peut exister dans le WABA sans être enregistré auprès de la
        // Cloud API : la lecture réussit, mais tout envoi échoue en 133010.
        $platform = data_get($response['data'], 'platform_type');

        if ($platform !== null && $platform !== 'CLOUD_API') {
            return $this->check_(
                'Numéro et jeton',
                'error',
                "Le numéro n'est pas enregistré auprès de la Cloud API (platform_type : {$platform}).",
                'Aucun envoi ne passera tant que ce numéro n\'est pas enregistré. Choisissez un PIN à six chiffres : il deviendra celui de la vérification en deux étapes du numéro.',
                action: 'register',
                doc: self::DOC_REGISTRATION,
            );
        }

        return $this->check_(
            'Numéro et jeton',
            'ok',
            sprintf(
                '%s — +%s, état %s, qualité %s.',
                data_get($response['data'], 'verified_name', 'Numéro connecté'),
                ltrim((string) data_get($response['data'], 'display_phone_number', '—'), '+'),
                strtolower((string) data_get($response['data'], 'status', 'inconnu')),
                strtolower((string) data_get($response['data'], 'quality_rating', 'inconnue')),
            ),
        );
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
            return $this->check_(
                'Abonnement aux webhooks',
                'error',
                $response['error'],
                'Vérifiez WHATSAPP_WABA_ID, et que le jeton porte la permission whatsapp_business_management.',
                doc: self::DOC_WEBHOOKS,
            );
        }

        $apps = data_get($response['data'], 'data', []);

        if (empty($apps)) {
            return $this->check_(
                'Abonnement aux webhooks',
                'error',
                "Aucune application n'est abonnée à ce compte WhatsApp Business.",
                'Aucun message entrant ne peut arriver. Abonnez l\'application, puis cochez le champ « messages » dans la configuration du webhook côté Meta.',
                action: 'subscribe',
                doc: self::DOC_WEBHOOKS,
            );
        }

        $names = collect($apps)
            ->map(fn ($app) => data_get($app, 'whatsapp_business_api_data.name'))
            ->filter()
            ->implode(', ');

        return $this->check_(
            'Abonnement aux webhooks',
            'ok',
            $names !== '' ? 'Application abonnée : '.$names.'.' : 'Une application est abonnée.',
        );
    }

    /**
     * Forme unique d'une vérification, pour que le front n'ait qu'une
     * structure à connaître.
     *
     * @return array<string, mixed>
     */
    private function check_(
        string $label,
        string $status,
        string $detail,
        ?string $advice = null,
        ?string $action = null,
        ?string $doc = null,
    ): array {
        return [
            'label' => $label,
            'status' => $status,
            'detail' => $detail,
            'advice' => $advice,
            'action' => $action,
            'doc' => $doc,
        ];
    }
}
