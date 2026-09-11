<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Deux contacts, quelques messages : l'interface ne doit pas être vide au
 * premier lancement. La première conversation a une fenêtre largement
 * ouverte, la seconde expire dans une heure — de quoi voir le bandeau jaune
 * sans attendre 23 heures.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->thread(
            waId: '241770000001',
            name: 'Awa Mbina',
            lastInboundAt: Carbon::now()->subMinutes(4),
            unread: 0,
            messages: [
                ['contact', 'Bonjour, vous êtes ouverts samedi ?', 6],
                ['bot', "Bonjour et bienvenue chez Ntchina Café ! Je suis l'assistant WhatsApp de la maison.\nÉcrivez « menu » pour voir ce que je sais faire.", 6],
                ['contact', 'horaires', 4],
                ['bot', "Nous sommes ouverts du lundi au samedi, de 8h à 19h.\nFermé le dimanche et les jours fériés.", 4],
            ],
        );

        $this->thread(
            waId: '241770000002',
            name: 'Serge Ondo',
            lastInboundAt: Carbon::now()->subHours(23),
            unread: 1,
            messages: [
                ['contact', 'Est-ce que je peux réserver une table pour huit personnes ?', 23 * 60],
                ['operator', 'Bien sûr, je vous réserve la grande table pour 19h.', 23 * 60 - 2],
            ],
        );
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: int}>  $messages
     */
    private function thread(string $waId, string $name, Carbon $lastInboundAt, int $unread, array $messages): void
    {
        $contact = Contact::updateOrCreate(['wa_id' => $waId], ['profile_name' => $name]);

        $conversation = Conversation::updateOrCreate(['contact_id' => $contact->id], [
            'last_inbound_at' => $lastInboundAt,
            'last_message_at' => $lastInboundAt,
            'unread_count' => $unread,
        ]);

        $conversation->messages()->delete();

        foreach ($messages as [$author, $body, $minutesAgo]) {
            $at = Carbon::now()->subMinutes($minutesAgo);
            $inbound = $author === Message::AUTHOR_CONTACT;

            $conversation->messages()->create([
                'wam_id' => 'wamid.demo.'.$waId.'.'.$minutesAgo,
                'direction' => $inbound ? Message::DIRECTION_INBOUND : Message::DIRECTION_OUTBOUND,
                'author' => $author,
                'body' => $body,
                'status' => $inbound ? Message::STATUS_DELIVERED : Message::STATUS_READ,
                'sent_at' => $at,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }

        $conversation->forceFill([
            'last_message_at' => Carbon::now()->subMinutes(min(array_column($messages, 2))),
        ])->save();
    }
}
