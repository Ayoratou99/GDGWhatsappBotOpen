<?php

namespace App\Services\Bot\Drivers;

use App\Models\Message;
use App\Services\Bot\Contracts\BotDriver;
use Illuminate\Support\Str;

/**
 * Règles de correspondance déclarées dans config/whatsapp.php : on peut les
 * modifier entre deux démos sans toucher au code.
 */
class KeywordBotDriver implements BotDriver
{
    public function reply(Message $inbound): ?string
    {
        $text = $this->normalize($inbound->body);

        foreach (config('whatsapp.keywords', []) as $rule) {
            foreach ($rule['match'] as $keyword) {
                if ($this->matches($text, $keyword)) {
                    return $rule['reply'];
                }
            }
        }

        return config('whatsapp.fallback');
    }

    /**
     * Minuscules, accents retirés, espaces normalisés : « Bonjour ! » et
     * « bonjour » doivent tomber sur la même règle.
     */
    private function normalize(string $value): string
    {
        return (string) preg_replace('/\s+/', ' ', trim(Str::ascii(Str::lower($value))));
    }

    /**
     * Frontières de mot : « salut » ne doit pas se déclencher sur « salutation ».
     */
    private function matches(string $text, string $keyword): bool
    {
        return (bool) preg_match('/\b'.preg_quote(Str::ascii(Str::lower($keyword)), '/').'\b/', $text);
    }
}
