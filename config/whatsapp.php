<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Meta WhatsApp Cloud API
    |--------------------------------------------------------------------------
    |
    | Ces valeurs viennent du tableau de bord Meta for Developers.
    | Aucun appel à env() ne doit exister en dehors de ce fichier : en
    | production la config est mise en cache et env() renverrait null.
    |
    */

    'token' => env('WHATSAPP_TOKEN'),
    'waba_id' => env('WHATSAPP_WABA_ID'),
    'phone_id' => env('WHATSAPP_PHONE_ID'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'app_secret' => env('WHATSAPP_APP_SECRET'),
    'api_version' => env('WHATSAPP_API_VERSION', 'v26.0'),
    'graph_url' => env('WHATSAPP_GRAPH_URL', 'https://graph.facebook.com'),

    /*
    |--------------------------------------------------------------------------
    | Fenêtre de service client
    |--------------------------------------------------------------------------
    |
    | Meta autorise l'envoi de texte libre pendant 24 h après le dernier
    | message entrant du contact. Au-delà, seul un template est accepté.
    |
    */

    'window_hours' => 24,

    /*
    |--------------------------------------------------------------------------
    | Bot
    |--------------------------------------------------------------------------
    |
    | mode : keyword (règles ci-dessous) ou ai (agent Laravel AI).
    | La commande « php artisan bot:mode {keyword|ai} » bascule à chaud.
    |
    */

    'bot' => [
        'enabled' => env('BOT_ENABLED', true),
        'mode' => env('BOT_MODE', 'keyword'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Règles du driver « keyword »
    |--------------------------------------------------------------------------
    |
    | La comparaison se fait sur le texte normalisé : minuscules, accents
    | retirés, espaces superflus supprimés. Deux lignes maximum par réponse,
    | elles s'affichent sur un vidéoprojecteur.
    |
    */

    'keywords' => [
        [
            'match' => ['bonjour', 'salut', 'hello', 'bonsoir', 'bjr'],
            'reply' => "Bonjour et bienvenue chez Ntchina Café ! Je suis l'assistant WhatsApp de la maison.\nÉcrivez « menu » pour voir ce que je sais faire.",
        ],
        [
            'match' => ['menu', 'aide', 'help', 'options'],
            'reply' => "Je peux vous renseigner sur : « horaires », « contact », « adresse ».\nUn conseiller peut aussi reprendre la conversation à tout moment.",
        ],
        [
            'match' => ['horaire', 'horaires', 'heure', 'heures', 'ouvert', 'ouverture'],
            'reply' => "Nous sommes ouverts du lundi au samedi, de 8h à 19h.\nFermé le dimanche et les jours fériés.",
        ],
        [
            'match' => ['contact', 'adresse', 'ou etes vous', 'telephone', 'appeler'],
            'reply' => "Ntchina Café — Boulevard Triomphal, Libreville.\nTéléphone : +241 77 00 00 00.",
        ],
    ],

    'fallback' => "Je n'ai pas encore appris à répondre à cela.\nÉcrivez « menu » pour voir les options disponibles.",

];
