<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->index()->constrained()->cascadeOnDelete();
            // Identifiant Meta (wamid.xxx). Unique : Meta réémet les webhooks
            // en cas d'échec, et c'est cet index qui empêche les doublons.
            $table->string('wam_id')->nullable()->unique();
            $table->enum('direction', ['inbound', 'outbound']);
            $table->enum('author', ['contact', 'bot', 'operator']);
            $table->text('body');
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            // Horodatage fourni par Meta, pas celui de notre insertion.
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
