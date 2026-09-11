<?php

namespace App\Ai\Agents;

use App\Ai\Tools\LookupContact;
use App\Models\Message as ConversationMessage;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Anthropic)]
#[Model('claude-sonnet-5')]
#[MaxSteps(4)]
#[MaxTokens(300)]
#[Timeout(30)]
class SupportAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param  ConversationMessage  $inbound  Le message auquel il faut répondre.
     */
    public function __construct(private ConversationMessage $inbound) {}

    public function instructions(): Stringable|string
    {
        $waId = $this->inbound->conversation->contact->wa_id;

        return <<<TEXT
        Tu es l'assistant WhatsApp d'une petite entreprise gabonaise, le Ntchina Café,
        situé boulevard Triomphal à Libreville et ouvert du lundi au samedi de 8h à 19h.

        Règles absolues :
        - réponds en français, en vouvoyant ;
        - deux phrases maximum ;
        - jamais de markdown : WhatsApp ne le rend pas, les astérisques s'afficheraient tels quels ;
        - si tu ne sais pas, propose de passer la main à un conseiller.

        Le numéro WhatsApp de la personne qui écrit est {$waId}. Utilise l'outil
        LookupContact avec ce numéro pour savoir si elle est déjà cliente, et adapte
        ta salutation en conséquence.
        TEXT;
    }

    /**
     * Historique de la conversation, en ordre chronologique. Le message auquel on
     * répond en est exclu : il est passé au prompt.
     *
     * Pas de RemembersConversations : l'historique vit déjà dans notre table
     * messages, et ce trait exigerait un modèle participant.
     */
    public function messages(): iterable
    {
        return $this->inbound->conversation
            ->messages()
            ->where('id', '<', $this->inbound->id)
            ->latest('id')
            ->limit(20)
            ->get()
            ->reverse()
            ->map(fn (ConversationMessage $message) => new Message(
                $message->isInbound() ? 'user' : 'assistant',
                $message->body,
            ))
            ->values()
            ->all();
    }

    public function tools(): iterable
    {
        return [new LookupContact];
    }
}
