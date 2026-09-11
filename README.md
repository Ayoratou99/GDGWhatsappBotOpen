# Console WhatsApp — démo GDG

Console minimale branchée sur l'API officielle **Meta WhatsApp Cloud** : les messages
arrivent en temps réel dans le navigateur, un bot répond automatiquement (mots-clés ou
IA), et l'opérateur peut reprendre la main tant que la **fenêtre de 24 heures** est
ouverte.

Le point pédagogique de la démo est cette fenêtre : un bandeau la décompte en direct au
dessus du fil, et verrouille la zone de saisie quand elle se ferme.

## Pile technique

Laravel 13 · PHP 8.4 · PostgreSQL 17 · Redis 7 · Reverb + Echo · Blade + Tailwind +
Alpine · Laravel AI SDK · Graph API v26.0

Trois tables (`contacts`, `conversations`, `messages`), pas de table `users` : un seul
compte opérateur défini dans l'environnement.

## Prérequis

- Docker et Docker Compose
- Une app Meta for Developers avec le produit **WhatsApp** ajouté
- Un tunnel HTTPS vers votre machine (`cloudflared`, `ngrok`…) pour recevoir les webhooks

## Démarrage

```bash
cp .env.example .env      # puis renseignez les clés Meta et le compte admin
docker compose run --rm app php artisan key:generate
docker compose up -d --build
```

La console est sur <http://localhost:8000>, Reverb sur le port 8080.

Chargez les données de démonstration pour ne pas ouvrir une interface vide :

```bash
docker compose exec app php artisan db:seed --force
```

Un `Makefile` regroupe les commandes courantes : `make up`, `make down`, `make logs`,
`make migrate`, `make seed`, `make tinker`, `make bot-ai`, `make bot-keyword`.
Sans `make` (Windows), chaque cible tient en une ligne `docker compose` — ouvrez le
fichier, les commandes sont directement recopiables.

## Configuration côté Meta

1. **Numéro** — WhatsApp → API Setup. Relevez le *Phone number ID* (`WHATSAPP_PHONE_ID`)
   et le *WhatsApp Business Account ID* (`WHATSAPP_WABA_ID`). Le jeton temporaire affiché
   ici expire en 24 h : pour une conférence, créez un *System User* et générez un jeton
   permanent (`WHATSAPP_TOKEN`).
2. **App Secret** — Paramètres → De base → *App Secret* (`WHATSAPP_APP_SECRET`). Il sert à
   valider la signature `X-Hub-Signature-256` de chaque webhook.
3. **Webhook** — exposez le port 8000 :

   ```bash
   cloudflared tunnel --url http://localhost:8000
   ```

   Dans WhatsApp → Configuration → Webhook :
   - *Callback URL* : `https://votre-tunnel/whatsapp/webhook`
   - *Verify token* : la valeur de `WHATSAPP_VERIFY_TOKEN`
   - Abonnez-vous au champ **messages** (sans cela, aucun webhook n'arrive).

   Meta appelle immédiatement l'URL en GET pour le handshake. Si la vérification échoue,
   regardez `storage/logs/laravel.log` : le refus y est tracé.

4. **Mode de l'app** — tant que l'app Meta est en mode *Développement*, seuls les numéros
   ayant un rôle sur l'app (administrateur, développeur, testeur) peuvent lui écrire. Un
   message envoyé depuis un autre téléphone n'arrivera jamais, et **ce n'est pas un bug de
   code**. Ajoutez le numéro du présentateur dans les rôles de l'app, ou passez l'app en
   mode *Live*.

## Temps réel derrière un tunnel

Le navigateur se connecte à Reverb avec les variables `VITE_REVERB_*`, **figées dans les
assets au moment du `npm run build`** — donc au moment du `docker compose build`.

- Console ouverte sur la machine du présentateur : les valeurs par défaut
  (`localhost:8080`) conviennent, seul le webhook passe par le tunnel.
- Console servie depuis un domaine public : renseignez `VITE_REVERB_HOST`,
  `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME` dans `.env` **puis reconstruisez l'image**
  (`make rebuild`). Sinon le navigateur cherchera un websocket sur `localhost` et la démo
  fonctionnera sur votre machine, nulle part ailleurs.

## Déroulé de la démo

1. **Ouvrir la console** sur le vidéoprojecteur, connecté avec `ADMIN_USERNAME` /
   `ADMIN_PASSWORD`. Laisser la liste des conversations visible.
2. **Envoyer « bonjour »** depuis le téléphone. Le message apparaît à gauche et dans le
   fil en moins de deux secondes, sans rafraîchissement. Le bandeau passe à 24:00:00.
3. **Le bot répond** : la bulle est bordée de teal et porte la mention « Bot ».
4. **Montrer les statuts** : sous la bulle sortante, *Envoyé* devient *Distribué*, puis
   *Lu* en vert dès que le téléphone ouvre la conversation.
5. **Reprendre la main** : taper une réponse dans la console. Elle arrive sur le téléphone,
   bordée de bleu — c'est l'opérateur humain, plus le bot.
6. **Basculer en IA** :

   ```bash
   make bot-ai        # ou : docker compose exec app php artisan bot:mode ai
   ```

   Laisser deux secondes au worker pour redémarrer, puis envoyer une question libre. La
   réponse est générée, et l'appel de l'outil `LookupContact` apparaît dans les logs :

   ```bash
   make logs
   ```

7. **La fenêtre de 24 h** : ouvrir la conversation de démonstration semée à 23 h
   d'ancienneté pour montrer le bandeau jaune, ou expliquer le verrouillage à partir du
   décompte affiché.

Pour tester l'envoi sans interface (avant la conférence, quand la salle est vide) :

```bash
docker compose exec app php artisan whatsapp:send 241770000000 "Test depuis la console"
```

## Fonctionnement interne

| Étape | Où ça se passe |
| --- | --- |
| Handshake `GET /whatsapp/webhook` | `WebhookController::verify` — lit `hub_mode`, `hub_verify_token`, `hub_challenge` |
| Signature `POST` | `VerifyWhatsAppSignature` — HMAC sur le corps brut, comparé avec `hash_equals` |
| Réponse à Meta | `WebhookController::handle` — met en file et répond 200 immédiatement |
| Traitement | `ProcessWhatsAppWebhook` → `WebhookProcessor` → `ConversationService` |
| Réponse automatique | `BotService` → `KeywordBotDriver` ou `AiBotDriver` |
| Envoi | `ConversationService` → `WhatsAppClient` → Graph API |
| Temps réel | `MessageReceived`, `MessageSent`, `MessageStatusUpdated` → Reverb → Echo |

Les doublons sont impossibles : `wam_id` porte un index unique et l'insertion passe par
`updateOrCreate`. Les statuts ne redescendent jamais : chaque statut a un rang, et seul un
rang supérieur — ou un échec — est écrit.

## Tests

```bash
docker compose exec app php artisan test
```

Couvrent le handshake, le refus d'une signature invalide, le rejeu d'un même payload, un
message non textuel ignoré, l'ordre des statuts, l'accès à la console et le verrouillage
hors fenêtre.

## Sécurité

`.env` n'est pas versionné et `.env.example` ne contient que des clés vides. Aucun jeton,
aucun identifiant ne doit entrer dans le dépôt. Les canaux de diffusion sont privés :
pendant une conférence, une app publique laisserait sinon n'importe qui lire les
conversations.
