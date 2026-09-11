<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            // Une conversation par contact : l'unicité est portée par la base.
            $table->foreignId('contact_id')->unique()->constrained()->cascadeOnDelete();
            // Détermine la fenêtre de 24 h.
            $table->timestamp('last_inbound_at')->nullable();
            // Sert uniquement au tri de la liste de gauche.
            $table->timestamp('last_message_at')->nullable();
            $table->integer('unread_count')->default(0);
            $table->timestamps();

            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
