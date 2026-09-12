<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Services\Bot\Drivers\KeywordBotDriver;
use Tests\TestCase;

class KeywordBotTest extends TestCase
{
    public function test_la_casse_et_les_accents_ne_changent_rien(): void
    {
        $this->assertStringContainsString('Festi', $this->reply('BONJOUR !'));
        $this->assertStringContainsString('19 décembre 2026', $this->reply('Vos horaires ?'));
        $this->assertStringContainsString('19 décembre 2026', $this->reply('c’est QUAND déjà ?'));
    }

    public function test_la_premiere_regle_qui_correspond_l_emporte(): void
    {
        // « menu » précède la règle des dates, qui contient aussi « ou ».
        $this->assertStringContainsString('Voici ce que je peux faire', $this->reply('menu ou inscription'));
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
