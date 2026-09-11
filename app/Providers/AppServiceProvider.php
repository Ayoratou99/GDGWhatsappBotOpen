<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
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
        // Derrière un proxy, l'application ne voit qu'une requête en clair et
        // fabriquerait des URL d'assets en http : le navigateur les bloquerait
        // sur une page servie en https, laissant l'interface sans style ni
        // JavaScript. APP_URL fait foi.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

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
