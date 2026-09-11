<?php

use Illuminate\Support\Facades\Broadcast;

/*
| Ces canaux restent privés : sur une app publique pendant une conférence,
| n'importe qui pourrait sinon lire les conversations. L'autorisation réelle
| est faite par le middleware « admin » déclaré dans bootstrap/app.php ;
| arrivé ici, l'opérateur est déjà authentifié.
*/

Broadcast::channel('conversations', fn () => true);

Broadcast::channel('conversation.{id}', fn ($user, $id) => true);
