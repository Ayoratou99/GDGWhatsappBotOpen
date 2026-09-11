#!/bin/sh
set -e

cd /var/www/html

# Sans cette attente, le worker démarre avant PostgreSQL et meurt en boucle.
# L'erreur est conservée : une base joignable mais refusant l'authentification
# ne doit pas se traduire par une attente silencieuse et sans fin.
attempt=1
max_attempts=30

until php -r '
    new PDO(
        "pgsql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"),
        getenv("DB_USERNAME"),
        getenv("DB_PASSWORD")
    );' 2>/tmp/pgsql-error; do

    if [ "$attempt" -ge "$max_attempts" ]; then
        echo "PostgreSQL injoignable après ${max_attempts} tentatives :"
        cat /tmp/pgsql-error
        echo "Hôte : ${DB_HOST}:${DB_PORT} — base : ${DB_DATABASE} — utilisateur : ${DB_USERNAME}"
        exit 1
    fi

    echo "En attente de PostgreSQL… (${attempt}/${max_attempts})"
    attempt=$((attempt + 1))
    sleep 1
done

# Un seul service porte APP_BOOTSTRAP : les migrations ne doivent tourner
# qu'une fois, même si trois conteneurs partagent cette image.
if [ "${APP_BOOTSTRAP:-false}" = "true" ]; then
    # Ne pas devancer un « key:generate » explicite : la clé serait produite
    # deux fois, et la première jetée sans que personne ne comprenne pourquoi.
    case "$*" in
        *key:generate*) ;;
        *)
            if ! grep -qE '^APP_KEY=base64:' .env 2>/dev/null; then
                php artisan key:generate --force
            fi
            ;;
    esac

    php artisan migrate --force
fi

# La clé vient peut-être d'être écrite dans .env : le processus courant ne la
# connaît pas encore.
if [ -f .env ]; then
    key=$(grep -E '^APP_KEY=base64:' .env | head -n 1 | cut -d= -f2-)
    [ -n "$key" ] && export APP_KEY="$key"
fi

exec "$@"
