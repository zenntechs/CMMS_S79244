#!/usr/bin/env bash

set -euo pipefail

cd /var/www/html

wait_for_postgres() {
    if [ "${DB_CONNECTION:-}" != "pgsql" ]; then
        return 0
    fi

    echo "Waiting for PostgreSQL at ${DB_HOST:-db}:${DB_PORT:-5432}..."

    for _ in $(seq 1 30); do
        if php -r '
            $dsn = sprintf(
                "pgsql:host=%s;port=%s;dbname=%s",
                getenv("DB_HOST") ?: "db",
                getenv("DB_PORT") ?: "5432",
                getenv("DB_DATABASE") ?: "cmms"
            );

            try {
                new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD"), [
                    PDO::ATTR_TIMEOUT => 2,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                exit(0);
            } catch (Throwable $exception) {
                exit(1);
            }
        '; then
            echo "PostgreSQL is ready."
            return 0
        fi

        sleep 2
    done

    echo "PostgreSQL did not become ready in time." >&2
    return 1
}

wait_for_postgres

mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

php artisan package:discover --ansi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

exec "$@"
