<?php

namespace App\Ai\Tools;

use App\Models\Contact;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Un seul outil, volontairement : il répond à la question « est-ce que la
 * personne qui écrit est déjà connue ? ».
 */
class LookupContact implements Tool
{
    public function description(): Stringable|string
    {
        return 'Vérifie si un numéro WhatsApp correspond à un contact déjà enregistré dans la base, et retourne son nom et son ancienneté le cas échéant.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'wa_id' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $waId = ltrim(trim((string) $request['wa_id']), '+');

        $contact = Contact::where('wa_id', $waId)->first();

        if (! $contact) {
            return 'Aucun contact enregistré pour ce numéro.';
        }

        return sprintf(
            'Contact connu : %s, enregistré depuis le %s, %d messages échangés.',
            $contact->displayName(),
            $contact->created_at->format('d/m/Y'),
            $contact->messages()->count(),
        );
    }
}
