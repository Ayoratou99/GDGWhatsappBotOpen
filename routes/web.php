<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

Route::middleware('admin')->group(function () {
    Route::redirect('/', '/conversations');

    Route::get('/connection-status', [ConversationController::class, 'status'])->name('connection.status');
    Route::post('/connection-status/repair', [ConversationController::class, 'repair'])->name('connection.repair');

    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'store'])->name('conversations.messages.store');
});

// Webhook Meta. Le GET n'est pas signé : il ne doit pas traverser le
// middleware de signature, sinon le handshake d'abonnement échoue.
Route::get('/whatsapp/webhook', [WebhookController::class, 'verify']);
Route::post('/whatsapp/webhook', [WebhookController::class, 'handle'])
    ->middleware('whatsapp.signature');
