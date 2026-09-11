<?php

use App\Http\Middleware\EnsureAdminIsAuthenticated;
use App\Http\Middleware\VerifyWhatsAppSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        // Aucun modèle User dans ce projet : c'est notre garde maison qui
        // autorise l'accès aux canaux privés, en amont des callbacks.
        ['middleware' => ['web', 'admin']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Meta ne connaît pas notre jeton CSRF.
        $middleware->validateCsrfTokens(except: ['whatsapp/webhook']);

        $middleware->alias([
            'whatsapp.signature' => VerifyWhatsAppSignature::class,
            'admin' => EnsureAdminIsAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
