#!/bin/sh
set -e

cd /var/www/html

# Sans cette attente, le worker démarre avant PostgreSQL et meurt en boucle.
until php -r '
    new PDO(
        "pgsql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"),
        getenv("DB_USERNAME"),
        getenv("DB_PASSWORD")
    );' >/dev/null 2>&1; do
    echo "En attente de PostgreSQL…"
    sleep 1
done

# Un seul service porte APP_BOOTSTRAP : les migrations ne doivent tourner
# qu'une fois, même si trois conteneurs partagent cette image.
if [ "${APP_BOOTSTRAP:-false}" = "true" ]; then
    if ! grep -qE '^APP_KEY=base64:' .env 2>/dev/null; then
        php artisan key:generate --force
    fi

    php artisan migrate --force
fi

# La clé vient peut-être d'être écrite dans .env : le processus courant ne la
# connaît pas encore.
if [ -f .env ]; then
    key=$(grep -E '^APP_KEY=base64:' .env | head -n 1 | cut -d= -f2-)
    [ -n "$key" ] && export APP_KEY="$key"
fi

exec "$@"
