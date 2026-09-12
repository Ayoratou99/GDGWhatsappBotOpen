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
    | Invitation à discuter
    |--------------------------------------------------------------------------
    |
    | Un numéro de test Meta n'apparaît dans aucun carnet d'adresses : la
    | conversation ne peut s'ouvrir que depuis l'API, par un modèle approuvé.
    | « hello_world » est fourni et pré-approuvé par Meta ; remplacez-le par le
    | vôtre dès que vous en avez un.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Format des numéros à l'envoi
    |--------------------------------------------------------------------------
    |
    | Le Gabon a ajouté un chiffre opérateur à son plan de numérotation, mais
    | WhatsApp conserve l'identifiant historique commençant par 0 : le webhook
    | annonce 241 02 94 36 87 là où le numéro actuel est 241 62 94 36 87. Meta
    | accepte le format actuel à l'envoi et le ramène lui-même vers le wa_id
    | historique — la preuve est dans sa réponse, qui renvoie « input » et
    | « wa_id » côte à côte.
    |
    | La conversion porte sur le chiffre qui suit le 0 : il désigne l'opérateur
    | et commande le préfixe à insérer. Videz « country » pour désactiver
    | entièrement ce mécanisme.
    |
    */

    'number_normalization' => [
        'country' => '241',
        'operators' => [
            // Moov Africa / Gabon Télécom
            '2' => '6',
            '5' => '6',
            '6' => '6',
            // Airtel
            '4' => '7',
            '7' => '7',
        ],
    ],

    /*
    | Exceptions nominatives, prioritaires sur la conversion ci-dessus. À
    | n'utiliser que pour un numéro qui échapperait à la règle.
    */

    'recipient_aliases' => [
        // '24102943687' => '24162943687',
    ],

    'invitation' => [
        'template' => env('WHATSAPP_INVITATION_TEMPLATE', 'hello_world'),
        'language' => env('WHATSAPP_INVITATION_LANGUAGE', 'en_US'),
    ],

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
    | pour rester lisibles d'un coup d'œil.
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
