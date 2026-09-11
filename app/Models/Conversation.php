<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = [
        'contact_id',
        'last_inbound_at',
        'last_message_at',
        'unread_count',
    ];

    protected function casts(): array
    {
        return [
            'last_inbound_at' => 'datetime',
            'last_message_at' => 'datetime',
            'unread_count' => 'integer',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Aperçu tronqué affiché dans la liste de gauche.
     */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Fin de la fenêtre de service client : 24 h après le dernier message
     * entrant. Null tant que le contact n'a jamais écrit.
     */
    public function windowExpiresAt(): ?CarbonInterface
    {
        return $this->last_inbound_at?->copy()->addHours(config('whatsapp.window_hours'));
    }

    /**
     * Hors de cette fenêtre, Meta refuse tout message en texte libre : il
     * faudrait passer par un template.
     */
    public function isWindowOpen(): bool
    {
        return $this->windowExpiresAt()?->isFuture() ?? false;
    }

    public function displayName(): string
    {
        return $this->contact->displayName();
    }

    /**
     * Forme unique d'une entrée de la liste de gauche : le rendu initial et
     * les événements diffusés parlent exactement le même langage.
     */
    public function toSidebarPayload(): array
    {
        $this->loadMissing(['contact', 'lastMessage']);

        return [
            'id' => $this->id,
            'name' => $this->displayName(),
            'wa_id' => $this->contact->wa_id,
            'preview' => $this->lastMessage?->body,
            'author' => $this->lastMessage?->author,
            'time' => $this->last_message_at?->format('H:i'),
            'unread_count' => $this->unread_count,
            'window_expires_at' => $this->windowExpiresAt()?->toIso8601String(),
        ];
    }
}
