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
        // Sans cette trace, l'appel du modèle à LookupContact resterait
        // invisible dans les logs.
        Event::listen(ToolInvoked::class, function (ToolInvoked $event) {
            Log::info('Outil IA invoqué.', [
                'tool' => $event->tool,
                'arguments' => $event->arguments,
            ]);
        });
    }
}
