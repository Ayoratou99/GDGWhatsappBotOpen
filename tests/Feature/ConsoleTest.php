<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('admin.username', 'gdg');
        config()->set('admin.password', 'mot-de-passe');
        config()->set('whatsapp.phone_id', '123456');

        Http::fake([
            '*' => Http::response(['messages' => [['id' => 'wamid.operateur']]], 200),
        ]);
    }

    public function test_la_console_est_fermee_sans_session(): void
    {
        $this->get('/conversations')->assertRedirect('/login');
    }

    public function test_les_bons_identifiants_ouvrent_la_console(): void
    {
        $this->post('/login', ['username' => 'gdg', 'password' => 'mot-de-passe'])
            ->assertRedirect('/conversations');

        $this->assertTrue(session('admin'));
    }

    public function test_les_mauvais_identifiants_sont_refuses(): void
    {
        $this->post('/login', ['username' => 'gdg', 'password' => 'faux'])
            ->assertSessionHasErrors('username');

        $this->assertNull(session('admin'));
    }

    public function test_l_operateur_repond_dans_une_fenetre_ouverte(): void
    {
        $conversation = $this->conversation(openedMinutesAgo: 10);

        $this->withSession(['admin' => true])
            ->post("/conversations/{$conversation->id}/messages", ['body' => 'Bonjour, je prends la suite.'])
            ->assertRedirect("/conversations/{$conversation->id}");

        $message = Message::where('author', Message::AUTHOR_OPERATOR)->sole();

        $this->assertSame(Message::STATUS_SENT, $message->status);
        $this->assertSame('wamid.operateur', $message->wam_id);
    }

    public function test_la_fenetre_fermee_bloque_la_reponse(): void
    {
        $conversation = $this->conversation(openedMinutesAgo: 25 * 60);

        $this->withSession(['admin' => true])
            ->post("/conversations/{$conversation->id}/messages", ['body' => 'Trop tard.'])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, Message::count());
    }

    public function test_ouvrir_un_fil_remet_le_compteur_de_non_lus_a_zero(): void
    {
        $conversation = $this->conversation(openedMinutesAgo: 10);
        $conversation->forceFill(['unread_count' => 3])->save();

        $this->withSession(['admin' => true])
            ->get("/conversations/{$conversation->id}")
            ->assertOk();

        $this->assertSame(0, $conversation->fresh()->unread_count);
    }

    private function conversation(int $openedMinutesAgo): Conversation
    {
        $contact = Contact::create(['wa_id' => '241770000009', 'profile_name' => 'Awa']);

        return Conversation::create([
            'contact_id' => $contact->id,
            'last_inbound_at' => now()->subMinutes($openedMinutesAgo),
            'last_message_at' => now()->subMinutes($openedMinutesAgo),
        ]);
    }
}
