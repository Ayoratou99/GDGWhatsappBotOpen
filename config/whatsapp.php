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
    | retirés, espaces superflus supprimés, et frontières de mots respectées —
    | « salut » ne se déclenche pas à l'intérieur de « salutation ». La
    | première règle qui correspond l'emporte : l'ordre compte.
    |
    */

    'keywords' => [
        [
            'match' => ['bonjour', 'salut', 'hello', 'bonsoir', 'bjr', 'festi', 'qui es-tu', 'presentation'],
            'reply' => "Salut ! Je suis Festi, la bouteille légendaire du DevFest Libreville. 🍾\nJe suis là pour t'informer sur le plus grand rendez-vous tech de l'année !\nÉcris « menu » ou « inscription » pour démarrer.",
        ],
        [
            'match' => ['menu', 'aide', 'help', 'options', 'infos'],
            'reply' => "Voici ce que je peux faire pour toi :\n- « inscription » : Réserver ta place\n- « date » ou « lieu » : Infos pratiques\n- « programme » ou « theme » : Découvrir les pistes\n- « contact » ou « reseaux » : Joindre le GDG Libreville",
        ],
        [
            'match' => ['inscrire', 'inscription', 'pass', 'ticket', 'billet', 'place', 'reserver', 'participer', 'lien'],
            'reply' => "🎟️ Les places sont limitées ! Le DevFest réunit développeurs, étudiants et créateurs le 19 Décembre 2026.\n👉 Réserve vite ta place ici : https://gdg.community.dev/events/details/google-gdg-libreville-presents-devfest-libreville-2026-creer-securiser-faire-evoluer-les-developpeurs-et-les-createurs-a-lere-agentique/",
        ],
        [
            'match' => ['quand', 'date', 'heure', 'horaires', 'lieu', 'adresse', 'emplacement', 'ou', 'tour aninf'],
            'reply' => "📅 Date : Samedi 19 décembre 2026, de 09h00 à 17h00 (GMT+1).\n📍 Lieu : Tour ANINF, Libreville.\n🎟️ N'attends pas, inscris-toi ici : https://gdg.community.dev/events/details/google-gdg-libreville-presents-devfest-libreville-2026-creer-securiser-faire-evoluer-les-developpeurs-et-les-createurs-a-lere-agentique/",
        ],
        [
            'match' => ['theme', 'sujet', 'programme', 'piste', 'track', 'developers', 'builders', 'ia', 'agentique', 'gemini'],
            'reply' => "💡 Thème : « Créer, sécuriser, faire évoluer à l'ère agentique ».\n2 pistes au choix :\n• Developers : IA générative, Gemini, sécurité, cloud...\n• Builders : Créer sans coder pour chefs de projet & designers.\n🎟️ Choisis ton parcours et inscris-toi : https://gdg.community.dev/events/details/google-gdg-libreville-presents-devfest-libreville-2026-creer-securiser-faire-evoluer-les-developpeurs-et-les-createurs-a-lere-agentique/",
        ],
        [
            'match' => ['pour qui', 'profil', 'etudiant', 'debutant', 'entrepreneur', 'designer'],
            'reply' => "🤝 Le DevFest est fait pour toi si tu es développeur, étudiant, entrepreneur, chef de produit, designer ou passionné d'IA !\n🎟️ Prends ta place maintenant : https://gdg.community.dev/events/details/google-gdg-libreville-presents-devfest-libreville-2026-creer-securiser-faire-evoluer-les-developpeurs-et-les-createurs-a-lere-agentique/",
        ],
        [
            'match' => ['contact', 'email', 'telephone', 'phone', 'joindre', 'organisateur', 'whatsapp'],
            'reply' => "📧 Email : gdglibreville@gmail.com\n📞 Tél/WhatsApp : +241 66127676 / +241 74213803\n🌐 Communauté : https://gdg.community.dev/gdg-libreville/",
        ],
        [
            'match' => ['reseaux', 'facebook', 'twitter', 'x', 'linkedin', 'youtube', 'social'],
            'reply' => "Suis le GDG Libreville sur nos réseaux :\n• Facebook : https://www.facebook.com/gdglibreville/\n• Twitter : https://twitter.com/GDGLibreville\n• LinkedIn : https://www.linkedin.com/company/google-developers-group-libreville\n• YouTube : https://youtube.com/@gdglibreville8339",
        ],
    ],

    'fallback' => "Je n'ai pas encore appris à répondre à cela.\nÉcris « menu » pour voir tout ce que je sais faire !",

];
