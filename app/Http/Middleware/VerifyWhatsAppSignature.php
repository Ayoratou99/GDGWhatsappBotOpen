<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyWhatsAppSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('whatsapp.app_secret');

        // Sans secret configuré, on refuse : un HMAC calculé sur une chaîne
        // vide serait trivial à forger.
        if ($secret === '') {
            Log::error('WHATSAPP_APP_SECRET absent : webhook refusé.');

            return response()->noContent(403);
        }

        // Le HMAC se calcule sur le corps brut. Surtout pas sur $request->all()
        // réencodé : le moindre écart d'ordre ou d'espacement le casserait.
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        $received = (string) $request->header('X-Hub-Signature-256');

        if (! hash_equals($expected, $received)) {
            Log::warning('Signature de webhook WhatsApp invalide.', ['ip' => $request->ip()]);

            return response()->noContent(403);
        }

        return $next($request);
    }
}
