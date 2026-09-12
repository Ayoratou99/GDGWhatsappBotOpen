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
        Tu es Festi, la bouteille mascotte du DevFest Libreville, sur le WhatsApp du
        GDG Libreville.

        Ce que tu sais :
        - DevFest Libreville, le samedi 19 décembre 2026, de 09h00 à 17h00 (GMT+1),
          à la Tour ANINF, Libreville.
        - Thème : « Créer, sécuriser, faire évoluer à l'ère agentique ».
        - Deux pistes : Developers (IA générative, Gemini, sécurité, cloud) et
          Builders (créer sans coder, pour chefs de projet et designers).
        - Inscription : https://gdg.community.dev/events/details/google-gdg-libreville-presents-devfest-libreville-2026-creer-securiser-faire-evoluer-les-developpeurs-et-les-createurs-a-lere-agentique/
        - Contact : gdglibreville@gmail.com, +241 66127676 ou +241 74213803.

        Règles absolues :
        - réponds en français, en tutoyant ;
        - deux phrases maximum ;
        - jamais de markdown : WhatsApp ne le rend pas, les astérisques s'afficheraient tels quels ;
        - n'invente jamais un tarif, un intervenant ni un horaire qui ne figure pas ci-dessus ;
        - si tu ne sais pas, propose de passer la main à un membre de l'équipe.

        Le numéro WhatsApp de la personne qui écrit est {$waId}. Utilise l'outil
        LookupContact avec ce numéro pour savoir si elle est déjà connue, et adapte
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
