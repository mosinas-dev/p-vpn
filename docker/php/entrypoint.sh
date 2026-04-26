#!/usr/bin/env bash
set -e

if [ -f composer.json ] && [ ! -d vendor ]; then
    echo "[entrypoint] vendor/ missing, running composer install"
    composer install --no-interaction --prefer-dist
fi

# Wait for MySQL to be reachable (healthcheck guarantees it, but we double-check
# from PHP's perspective — DNS, credentials, db existence).
if [ -n "${DB_HOST:-}" ]; then
    echo "[entrypoint] waiting for database ${DB_HOST}:${DB_PORT:-3306}…"
    for i in $(seq 1 30); do
        if php -r "try{new PDO('mysql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT')?:3306).';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));exit(0);}catch(Throwable \$e){exit(1);}" 2>/dev/null; then
            echo "[entrypoint] database is up"
            break
        fi
        sleep 1
    done
fi

# Auto-run migrations on the app container (php-fpm). Other roles (queue worker,
# cron) skip this — they'll wait until app finishes.
if [ "${RUN_MIGRATIONS:-0}" = "1" ] && [ -f artisan ]; then
    echo "[entrypoint] running migrations"
    php artisan migrate --force --no-interaction || true
fi

# For non-app roles (queue, scheduler), wait until the migrations table contains
# the basic tables — otherwise queue:work would crash on missing `cache`/`jobs`.
if [ "${WAIT_FOR_MIGRATIONS:-0}" = "1" ]; then
    echo "[entrypoint] waiting for migrations to complete…"
    for i in $(seq 1 60); do
        if php -r "try{\$p=new PDO('mysql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT')?:3306).';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));\$s=\$p->query(\"SHOW TABLES LIKE 'cache'\");exit(\$s && \$s->fetch() ? 0 : 1);}catch(Throwable \$e){exit(1);}" 2>/dev/null; then
            echo "[entrypoint] migrations ready"
            break
        fi
        sleep 1
    done
fi

exec "$@"
