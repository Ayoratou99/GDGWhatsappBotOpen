<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    public const DIRECTION_INBOUND = 'inbound';

    public const DIRECTION_OUTBOUND = 'outbound';

    public const AUTHOR_CONTACT = 'contact';

    public const AUTHOR_BOT = 'bot';

    public const AUTHOR_OPERATOR = 'operator';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_READ = 'read';

    public const STATUS_FAILED = 'failed';

    /**
     * Les webhooks de statut arrivent parfois dans le désordre : un
     * « delivered » peut suivre un « read ». On ne rétrograde jamais.
     */
    public const STATUS_RANK = [
        self::STATUS_PENDING => 0,
        self::STATUS_SENT => 1,
        self::STATUS_DELIVERED => 2,
        self::STATUS_READ => 3,
        self::STATUS_FAILED => 4,
    ];

    protected $fillable = [
        'conversation_id',
        'wam_id',
        'direction',
        'author',
        'body',
        'status',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function isInbound(): bool
    {
        return $this->direction === self::DIRECTION_INBOUND;
    }

    public function isFromBot(): bool
    {
        return $this->author === self::AUTHOR_BOT;
    }

    /**
     * Un nouveau statut n'est écrit que s'il apporte une information : il
     * progresse, ou c'est un échec — qui écrase toujours le reste.
     */
    public static function statusOutranks(string $new, string $current): bool
    {
        if ($new === self::STATUS_FAILED) {
            return true;
        }

        return (self::STATUS_RANK[$new] ?? 0) > (self::STATUS_RANK[$current] ?? 0);
    }

    /**
     * Libellé lisible à cinq mètres : pas de coches grises minuscules.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Envoi…',
            self::STATUS_SENT => 'Envoyé',
            self::STATUS_DELIVERED => 'Distribué',
            self::STATUS_READ => 'Lu',
            self::STATUS_FAILED => 'Échec',
            default => $this->status,
        };
    }

    /**
     * Charge utile unique, partagée par les trois événements diffusés et par
     * le rendu Blade : le front n'a qu'une forme de message à connaître.
     */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'wam_id' => $this->wam_id,
            'direction' => $this->direction,
            'author' => $this->author,
            'body' => $this->body,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'error_message' => $this->error_message,
            'time' => $this->created_at?->format('H:i'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
