<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Contact extends Model
{
    protected $fillable = [
        'wa_id',
        'profile_name',
    ];

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function messages(): HasManyThrough
    {
        return $this->hasManyThrough(Message::class, Conversation::class);
    }

    /**
     * Nom affiché dans l'interface : le nom WhatsApp si Meta nous l'a donné,
     * sinon le numéro au format international.
     */
    public function displayName(): string
    {
        return $this->profile_name ?: '+'.$this->wa_id;
    }
}
