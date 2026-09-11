<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Vérifie l'envoi sortant sans passer par l'interface : utile pour isoler un
 * problème de jeton, de numéro ou de configuration.
 */
class SendWhatsAppMessage extends Command
{
    protected $signature = 'whatsapp:send {to : Numéro international sans + (ex. 241770000000)} {body : Texte du message}';

    protected $description = 'Envoie un message texte via la Cloud API de Meta.';

    public function handle(WhatsAppClient $client): int
    {
        try {
            $response = $client->sendText($this->argument('to'), $this->argument('body'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Message accepté par Meta.');
        $this->components->twoColumnDetail('wam_id', (string) data_get($response, 'messages.0.id', '—'));

        return self::SUCCESS;
    }
}
