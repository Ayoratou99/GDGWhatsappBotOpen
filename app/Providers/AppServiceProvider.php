<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Events\ToolInvoked;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Montrer que l'outil a bien été appelé fait partie de la démo : sans
        // cette trace, l'appel du modèle à LookupContact reste invisible.
        Event::listen(ToolInvoked::class, function (ToolInvoked $event) {
            Log::info('Outil IA invoqué.', [
                'tool' => $event->tool,
                'arguments' => $event->arguments,
            ]);
        });
    }
}
