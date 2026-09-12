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

Générer la clé applicative, une fois. Les trois conteneurs applicatifs démarrent en
parallèle et lisent tous cette clé dans `.env` : elle doit y être avant, sinon le worker
et Reverb peuvent démarrer sans elle.

```bash
docker compose run --rm app php artisan key:generate
```

```bash
docker compose up -d
```

Les migrations sont appliquées automatiquement au démarrage. Données d'exemple,
facultatives :

```bash
docker compose exec app php artisan db:seed --force
```

La console écoute sur le port 8000, Reverb sur le 8080, tous deux liés à `127.0.0.1`.

**Si un de ces ports est déjà occupé sur la machine**, ne touchez pas à `DB_PORT` ni à
`REDIS_PORT` — ils décrivent le réseau interne de Docker. Ce sont ces quatre variables qui
commandent la publication vers l'hôte :

```dotenv
APP_PORT=8000
REVERB_FORWARD_PORT=8080
DB_FORWARD_PORT=5432
REDIS_FORWARD_PORT=6379
```

## Mettre à jour

Après avoir récupéré une nouvelle version du code :

```bash
docker compose up -d --build --renew-anon-volumes
```

`--renew-anon-volumes` n'est pas facultatif. `vendor/` et `public/build` vivent dans des
volumes anonymes qui préservent ce que l'image a construit — sans quoi le montage du code
les masquerait. Compose conserve ces volumes d'une recréation à l'autre : sans cette
option, une image fraîchement reconstruite continue de servir les assets de la
précédente, et l'interface s'affiche sans style.

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

Servie en HTTPS, la console refusera un websocket en clair : le temps réel doit passer par
le même hôte, en `wss` sur 443. Côté `.env`, **avant** la construction de l'image :

```dotenv
APP_URL=https://votre-domaine
VITE_REVERB_HOST=votre-domaine
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

Côté reverse proxy, deux routes — la seconde est celle qu'on oublie, et sans elle la page
s'affiche parfaitement sans que rien n'arrive jamais :

```nginx
location / {
    proxy_pass http://127.0.0.1:8000;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}

# Websocket Reverb. Ce préfixe couvre /app/{clé} et /apps/{id}/events.
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 3600s;
    proxy_send_timeout 3600s;
}
```

Les ports 8000 et 8080 ne sont publiés que sur `127.0.0.1` : ajustez `APP_PORT` et
`REVERB_FORWARD_PORT` s'ils sont déjà occupés sur la machine.

Tant que l'app Meta est en mode *Développement*, seuls les numéros disposant d'un rôle sur
l'app peuvent échanger avec elle. Passer l'app en mode *Live* pour ouvrir l'accès.

Les numéros gabonais sont convertis avant l'envoi : WhatsApp conserve l'identifiant
historique en `0` (`24102943687`) alors que Meta attend le format actuel avec chiffre
opérateur (`24162943687`). La règle est déclarée dans `config/whatsapp.php`, sous
`number_normalization`, et chaque conversion est tracée dans les logs. Videz la clé
`country` pour la désactiver hors du Gabon.

`.env` n'est pas versionné ; `.env.example` ne contient que des clés vides.
