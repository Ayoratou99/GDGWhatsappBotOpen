<?php

namespace Tests\Feature;

use App\Jobs\ProcessWhatsAppWebhook;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('whatsapp.verify_token', 'jeton-de-verification');
        config()->set('whatsapp.app_secret', 'secret-de-l-app');
    }

    public function test_le_handshake_retourne_le_challenge_brut(): void
    {
        // PHP remplace les points par des underscores : hub.mode → hub_mode.
        $response = $this->get('/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=jeton-de-verification&hub_challenge=1158201444');

        $response->assertOk();
        $this->assertSame('1158201444', $response->getContent());
    }

    public function test_le_handshake_est_refuse_avec_un_mauvais_jeton(): void
    {
        $this->get('/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=faux&hub_challenge=123')
            ->assertForbidden();
    }

    public function test_une_signature_invalide_recoit_un_403(): void
    {
        Queue::fake();

        $this->withHeaders(['X-Hub-Signature-256' => 'sha256='.str_repeat('0', 64)])
            ->postJson('/whatsapp/webhook', ['object' => 'whatsapp_business_account'])
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_une_signature_valide_met_le_payload_en_file(): void
    {
        Queue::fake();

        $payload = ['object' => 'whatsapp_business_account'];
        $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), 'secret-de-l-app');

        $this->withHeaders(['X-Hub-Signature-256' => $signature])
            ->postJson('/whatsapp/webhook', $payload)
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        Queue::assertPushed(ProcessWhatsAppWebhook::class);
    }
}
