<?php

namespace App\Services\Bot\Drivers;

use App\Ai\Agents\SupportAgent;
use App\Models\Message;
use App\Services\Bot\Contracts\BotDriver;
use Illuminate\Support\Facades\Log;

class AiBotDriver implements BotDriver
{
    public function reply(Message $inbound): ?string
    {
        $response = (new SupportAgent($inbound))->prompt($inbound->body);

        $text = trim((string) $response);

        Log::info('Réponse générée par le modèle.', [
            'conversation_id' => $inbound->conversation_id,
            'characters' => mb_strlen($text),
        ]);

        return $text !== '' ? $text : null;
    }
}
