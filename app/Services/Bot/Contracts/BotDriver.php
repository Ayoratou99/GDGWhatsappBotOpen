<?php

namespace App\Services\Bot\Contracts;

use App\Models\Message;

interface BotDriver
{
    /**
     * Retourne le texte à envoyer, ou null pour ne rien répondre.
     */
    public function reply(Message $inbound): ?string;
}
