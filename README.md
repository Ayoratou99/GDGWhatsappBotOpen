# Console WhatsApp

Console web branchée sur l'API WhatsApp Cloud de Meta : réception des messages en temps
réel, réponse automatique par mots-clés ou par IA, et reprise en main par un opérateur
tant que la fenêtre de 24 heures est ouverte.

Laravel 13 · PHP 8.4 · PostgreSQL 17 · Redis 7 · Reverb + Echo · Blade, Tailwind, Alpine ·
Graph API v26.0

## Prérequis

- Docker et Docker Compose
- Une app Meta for Developers avec le produit WhatsApp activé
- Un domaine ou une IP publique servie en HTTPS : Meta n'accepte que des webhooks `https`

## Déploiement

```bash
cp .env.example .env
```

Renseigner dans `.env` :

| Variable | Valeur |
| --- | --- |
| `WHATSAPP_TOKEN` | Jeton d'accès de l'app (System User pour un jeton permanent) |
| `WHATSAPP_WABA_ID` | WhatsApp Business Account ID |
| `WHATSAPP_PHONE_ID` | Phone number ID du numéro émetteur |
| `WHATSAPP_VERIFY_TOKEN` | Chaîne libre, à recopier dans la console Meta |
| `WHATSAPP_APP_SECRET` | App Secret, onglet Paramètres → De base |
| `ADMIN_USERNAME` / `ADMIN_PASSWORD` | Compte d'accès à la console |
| `ANTHROPIC_API_KEY` | Requis uniquement si `BOT_MODE=ai` |
| `VITE_REVERB_HOST` | Domaine ou IP publique — jamais `localhost` |

```bash
docker compose build
```

```bash
docker compose up -d
```

Les migrations et la clé applicative sont appliquées automatiquement au démarrage.
Données d'exemple, facultatives :

```bash
docker compose exec app php artisan db:seed --force
```

La console écoute sur le port 8000, Reverb sur le 8080.

## Webhook Meta

Dans WhatsApp → Configuration → Webhook :

- **Callback URL** : `https://votre-domaine/whatsapp/webhook`
- **Verify token** : la valeur de `WHATSAPP_VERIFY_TOKEN`
- **Champ à abonner** : `messages`

Contrôle du handshake, qui doit répondre `200` avec le challenge en corps :

```bash
curl -i "http://localhost:8000/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=VOTRE_VERIFY_TOKEN&hub_challenge=12345"
```

Contrôle de l'envoi sortant :

```bash
docker compose exec app php artisan whatsapp:send 241XXXXXXXX "Message de test"
```

## Commandes

| Commande | Effet |
| --- | --- |
| `make up` | Construit et démarre la pile |
| `make down` | Arrête la pile |
| `make rebuild` | Reconstruit l'image et rafraîchit `vendor/` et `public/build` |
| `make logs` | Suit les logs de `app`, `queue` et `reverb` |
| `make migrate` | Applique les migrations |
| `make seed` | Charge les données d'exemple |
| `make fresh` | Réinitialise la base |
| `make test` | Lance la suite de tests |
| `make tinker` | Ouvre une console Tinker |
| `make bot-keyword` | Bascule le bot en mode mots-clés |
| `make bot-ai` | Bascule le bot en mode IA |

Sans `make`, chaque cible tient en une ligne `docker compose` : le fichier `Makefile` les
donne telles quelles.

## À savoir avant la mise en ligne

Les variables `VITE_*` sont compilées dans les assets : toute modification impose un
`docker compose build` suivi d'un `docker compose up -d`.

Si la console est servie en HTTPS, le navigateur refusera un websocket en clair. Proxifier
`/app` vers `reverb:8080` en `wss`, puis fixer `VITE_REVERB_PORT=443` et
`VITE_REVERB_SCHEME=https` **avant** la construction de l'image.

Tant que l'app Meta est en mode *Développement*, seuls les numéros disposant d'un rôle sur
l'app peuvent échanger avec elle. Passer l'app en mode *Live* pour ouvrir l'accès.

`.env` n'est pas versionné ; `.env.example` ne contient que des clés vides.
