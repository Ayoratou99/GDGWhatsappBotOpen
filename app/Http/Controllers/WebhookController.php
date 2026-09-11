<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    /**
     * Handshake d'abonnement. Meta appelle l'URL avec hub.mode,
     * hub.verify_token et hub.challenge — mais PHP transforme les points en
     * underscores dans $_GET : on lit donc hub_mode, hub_verify_token et
     * hub_challenge. C'est la première cause d'échec de cette étape.
     */
    public function verify(Request $request): Response
    {
        $token = (string) config('whatsapp.verify_token');

        if ($token !== ''
            && $request->query('hub_mode') === 'subscribe'
            && hash_equals($token, (string) $request->query('hub_verify_token'))) {
            // Le challenge, brut, rien d'autre : ni JSON, ni vue.
            return response((string) $request->query('hub_challenge'), 200)
                ->header('Content-Type', 'text/plain');
        }

        Log::warning('Handshake de webhook WhatsApp refusé.', ['ip' => $request->ip()]);

        return response()->noContent(403);
    }

    /**
     * Trois lignes et rien d'autre : Meta considère l'appel échoué si la
     * réponse tarde. Tout le traitement part dans un job.
     */
    public function handle(Request $request): JsonResponse
    {
        ProcessWhatsAppWebhook::dispatch($request->all());

        return response()->json(['status' => 'ok'], 200);
    }
}
