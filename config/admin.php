<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Compte unique de la console
    |--------------------------------------------------------------------------
    |
    | La console n'a ni table users ni modèle User : un seul opérateur, dont
    | les identifiants vivent dans l'environnement. La session porte
    | simplement le drapeau « admin ».
    |
    */

    'username' => env('ADMIN_USERNAME'),
    'password' => env('ADMIN_PASSWORD'),

];
