#!/bin/sh
# Start każdego kontenera aplikacji: cache konfiguracji, tras i widoków z bieżących zmiennych środowiskowych.
set -e

cd /var/www/html

php artisan optimize --no-interaction > /dev/null

exec "$@"
