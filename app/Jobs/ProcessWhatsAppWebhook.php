<?php

namespace App\Jobs;

use App\Services\WhatsApp\WebhookProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Tout le travail réel du webhook vit ici : Meta considère l'appel échoué si
 * la réponse tarde, et réessaie pendant sept jours.
 */
class ProcessWhatsAppWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    public function handle(WebhookProcessor $processor): void
    {
        $processor->process($this->payload);
    }
}
