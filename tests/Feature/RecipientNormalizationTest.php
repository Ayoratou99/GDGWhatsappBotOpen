<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Services\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecipientNormalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('whatsapp.phone_id', '123456');

        Http::fake([
            '*' => Http::response(['messages' => [['id' => 'wamid.sortant']]], 200),
        ]);
    }

    public function test_un_wa_id_gabonais_moov_part_au_format_actuel(): void
    {
        $this->reply('24102943687');

        // 241 0[2] … devient 241 [6]2 …
        Http::assertSent(fn ($request) => $request['to'] === '24162943687');
    }

    public function test_un_wa_id_gabonais_airtel_prend_le_prefixe_7(): void
    {
        $this->reply('24107123456');

        Http::assertSent(fn ($request) => $request['to'] === '24177123456');
    }

    public function test_un_numero_deja_au_format_actuel_reste_intact(): void
    {
        $this->reply('24162943687');

        Http::assertSent(fn ($request) => $request['to'] === '24162943687');
    }

    public function test_un_numero_hors_gabon_reste_intact(): void
    {
        $this->reply('33601020304');

        Http::assertSent(fn ($request) => $request['to'] === '33601020304');
    }

    public function test_une_exception_nominative_prime_sur_la_regle(): void
    {
        config()->set('whatsapp.recipient_aliases', ['24102943687' => '24199999999']);

        $this->reply('24102943687');

        Http::assertSent(fn ($request) => $request['to'] === '24199999999');
    }

    private function reply(string $waId): void
    {
        $contact = Contact::create(['wa_id' => $waId]);

        $conversation = Conversation::create([
            'contact_id' => $contact->id,
            'last_inbound_at' => now()->subMinutes(5),
            'last_message_at' => now()->subMinutes(5),
        ]);

        app(ConversationService::class)->replyAsOperator($conversation, 'Bonjour.');
    }
}
