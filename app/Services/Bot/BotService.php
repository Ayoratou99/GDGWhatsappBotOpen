<?php

namespace App\Services\Bot;

use App\Models\Message;
use App\Services\Bot\Contracts\BotDriver;
use App\Services\Bot\Drivers\AiBotDriver;
use App\Services\Bot\Drivers\KeywordBotDriver;
use App\Services\ConversationService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Point d'entrée unique du bot. Décide s'il faut répondre, choisit le driver,
 * puis confie l'envoi à ConversationService.
 */
class BotService
{
    public function __construct(private ConversationService $conversations) {}

    public function handle(Message $inbound): void
    {
        if (! config('whatsapp.bot.enabled')) {
            Log::info('Bot désactivé, aucune réponse automatique.', ['message_id' => $inbound->id]);

            return;
        }

        $conversation = $inbound->conversation;

        if (! $conversation->isWindowOpen()) {
            Log::warning('Fenêtre de 24 h fermée, le bot ne peut pas répondre.', [
                'conversation_id' => $conversation->id,
            ]);

            return;
        }

        $reply = $this->askDriver($inbound);

        if (blank($reply)) {
            return;
        }

        $this->conversations->replyAsBot($conversation, trim($reply));
    }

    /**
     * Un modèle indisponible ne doit pas casser la démo : on log l'erreur et
     * on retombe sur les mots-clés, qui répondent toujours.
     */
    private function askDriver(Message $inbound): ?string
    {
        $mode = (string) config('whatsapp.bot.mode', 'keyword');

        try {
            return $this->driver($mode)->reply($inbound);
        } catch (Throwable $exception) {
            Log::error('Le driver du bot a échoué.', [
                'mode' => $mode,
                'message' => $exception->getMessage(),
            ]);

            return $mode === 'keyword' ? null : app(KeywordBotDriver::class)->reply($inbound);
        }
    }

    private function driver(string $mode): BotDriver
    {
        return match ($mode) {
            'ai' => app(AiBotDriver::class),
            default => app(KeywordBotDriver::class),
        };
    }
}
