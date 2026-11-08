#!/bin/sh
set -eu
if [ ! -f .env ]; then
    cp .env.example .env
fi
if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi
php artisan migrate --force --no-interaction
exec "$@"
