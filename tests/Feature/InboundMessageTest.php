<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Message;
use App\Services\WhatsApp\WebhookProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InboundMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('whatsapp.phone_id', '123456');
        config()->set('whatsapp.token', 'jeton');

        Http::fake([
            '*' => Http::response(['messages' => [['id' => 'wamid.sortant']]], 200),
        ]);
    }

    public function test_un_message_texte_cree_le_contact_la_conversation_et_le_message(): void
    {
        $this->process($this->textPayload('wamid.entrant.1', 'Bonjour'));

        $this->assertDatabaseHas('contacts', ['wa_id' => '241770000009', 'profile_name' => 'Awa']);
        $this->assertDatabaseHas('messages', [
            'wam_id' => 'wamid.entrant.1',
            'direction' => Message::DIRECTION_INBOUND,
            'author' => Message::AUTHOR_CONTACT,
            'body' => 'Bonjour',
        ]);

        $conversation = Contact::first()->conversation;
        $this->assertNotNull($conversation->last_inbound_at);
        $this->assertTrue($conversation->isWindowOpen());
    }

    public function test_le_bot_repond_automatiquement(): void
    {
        $this->process($this->textPayload('wamid.entrant.2', 'Bonjour'));

        $reply = Message::where('author', Message::AUTHOR_BOT)->sole();

        $this->assertSame(Message::DIRECTION_OUTBOUND, $reply->direction);
        $this->assertSame(Message::STATUS_SENT, $reply->status);
        $this->assertSame('wamid.sortant', $reply->wam_id);
    }

    public function test_rejouer_le_meme_payload_ne_cree_pas_de_doublon(): void
    {
        $payload = $this->textPayload('wamid.entrant.3', 'Bonjour');

        $this->process($payload);
        $this->process($payload);

        $this->assertSame(1, Message::where('wam_id', 'wamid.entrant.3')->count());
        // Le bot ne doit pas répondre une seconde fois non plus.
        $this->assertSame(1, Message::where('author', Message::AUTHOR_BOT)->count());
    }

    public function test_un_message_non_textuel_est_ignore_proprement(): void
    {
        $payload = $this->textPayload('wamid.image.1', 'peu importe');
        $payload['entry'][0]['changes'][0]['value']['messages'][0] = [
            'from' => '241770000009',
            'id' => 'wamid.image.1',
            'timestamp' => (string) now()->timestamp,
            'type' => 'image',
            'image' => ['id' => '999', 'mime_type' => 'image/jpeg'],
        ];

        $this->process($payload);

        $this->assertSame(0, Message::count());
    }

    public function test_un_statut_ne_redescend_jamais(): void
    {
        $this->process($this->textPayload('wamid.entrant.4', 'Bonjour'));

        $this->process($this->statusPayload('wamid.sortant', 'read'));
        $this->process($this->statusPayload('wamid.sortant', 'delivered'));

        $this->assertSame(Message::STATUS_READ, Message::where('wam_id', 'wamid.sortant')->sole()->status);
    }

    public function test_un_echec_ecrase_toujours_le_statut_courant(): void
    {
        $this->process($this->textPayload('wamid.entrant.5', 'Bonjour'));

        $this->process($this->statusPayload('wamid.sortant', 'read'));
        $this->process($this->statusPayload('wamid.sortant', 'failed'));

        $message = Message::where('wam_id', 'wamid.sortant')->sole();

        $this->assertSame(Message::STATUS_FAILED, $message->status);
    }

    private function process(array $payload): void
    {
        app(WebhookProcessor::class)->process($payload);
    }

    private function textPayload(string $wamId, string $body): array
    {
        return $this->envelope([
            'contacts' => [[
                'profile' => ['name' => 'Awa'],
                'wa_id' => '241770000009',
            ]],
            'messages' => [[
                'from' => '241770000009',
                'id' => $wamId,
                'timestamp' => (string) now()->timestamp,
                'type' => 'text',
                'text' => ['body' => $body],
            ]],
        ]);
    }

    private function statusPayload(string $wamId, string $status): array
    {
        return $this->envelope([
            'statuses' => [[
                'id' => $wamId,
                'status' => $status,
                'timestamp' => (string) now()->timestamp,
                'recipient_id' => '241770000009',
            ]],
        ]);
    }

    private function envelope(array $value): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '000',
                'changes' => [[
                    'field' => 'messages',
                    'value' => array_merge([
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['display_phone_number' => '241770000000', 'phone_number_id' => '123456'],
                    ], $value),
                ]],
            ]],
        ];
    }
}
