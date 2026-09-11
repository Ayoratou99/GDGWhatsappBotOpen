<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Services\Bot\Drivers\KeywordBotDriver;
use Tests\TestCase;

class KeywordBotTest extends TestCase
{
    public function test_la_casse_et_les_accents_ne_changent_rien(): void
    {
        $this->assertStringContainsString('bienvenue', $this->reply('BONJOUR !'));
        $this->assertStringContainsString('8h à 19h', $this->reply('Vos horaires ?'));
        $this->assertStringContainsString('8h à 19h', $this->reply('quelle est votre HEURE d’ouverture'));
    }

    public function test_les_mots_cles_respectent_les_frontieres_de_mots(): void
    {
        // « salut » ne doit pas se déclencher au milieu de « salutation ».
        $this->assertSame(config('whatsapp.fallback'), $this->reply('Je cherche une salutation formelle.'));
    }

    public function test_un_texte_inconnu_recoit_le_message_de_repli(): void
    {
        $this->assertSame(config('whatsapp.fallback'), $this->reply('Combien coûte une fusée ?'));
    }

    private function reply(string $body): string
    {
        $message = new Message(['body' => $body]);

        return (string) app(KeywordBotDriver::class)->reply($message);
    }
}
