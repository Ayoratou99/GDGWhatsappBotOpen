# syntax=docker/dockerfile:1

# --- Étape 1 : compilation des assets ---------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json* ./
# npm ci si le verrou est versionné, npm install sinon : le premier build d'un
# clone frais ne doit pas échouer pour un fichier absent.
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi

COPY vite.config.js ./
COPY resources ./resources

# Ces valeurs sont figées dans le bundle : le navigateur ne les relira jamais.
# Derrière un tunnel, passez l'hôte public au build, pas localhost.
ARG VITE_REVERB_APP_KEY=local-key
ARG VITE_REVERB_HOST=localhost
ARG VITE_REVERB_PORT=8080
ARG VITE_REVERB_SCHEME=http

ENV VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

RUN npm run build

# --- Étape 2 : application ---------------------------------------------------
FROM php:8.4-cli AS app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS git unzip libpq-dev libzip-dev libicu-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql zip intl bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
COPY --from=assets /app/public/build ./public/build

# Reverb et le SDK IA sont ajoutés ici plutôt que figés dans composer.lock : le
# verrou du dépôt a été produit avant eux, et Reverb impose guzzlehttp/psr7 ^2.6
# là où ce verrou fige la 3.x. Une mise à jour partielle ne peut pas rétrograder
# un paquet verrouillé : on laisse donc composer résoudre l'arbre complet, avec
# la connexion du serveur de build.
#
# --no-scripts : le manifeste des paquets est reconstruit au premier boot,
# sans dépendre d'un .env au moment du build.
RUN rm -f composer.lock \
    && composer require laravel/reverb laravel/ai --with-all-dependencies \
        --no-interaction --no-scripts --update-no-dev --optimize-autoloader \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8000 8080

ENTRYPOINT ["entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
