#!/bin/bash
set -e

cd /app

echo "=> Deployment Type: ${DEPLOYMENT_TYPE:-web}"

# 1. Wait for Database & Redis connectivity
if [ -n "$DB_HOST" ]; then
  echo "=> Waiting for Database (${DB_HOST}:${DB_PORT:-5432})..."
  until timeout 1 bash -c "cat < /dev/null > /dev/tcp/${DB_HOST}/${DB_PORT:-5432}" 2>/dev/null; do
    sleep 1
  done
  echo "=> Database reachable."
fi

if [ -n "$REDIS_HOST" ]; then
  echo "=> Waiting for Redis (${REDIS_HOST}:${REDIS_PORT:-6379})..."
  until timeout 1 bash -c "cat < /dev/null > /dev/tcp/${REDIS_HOST}/${REDIS_PORT:-6379}" 2>/dev/null; do
    sleep 1
  done
  echo "=> Redis reachable."
fi

# 2. Worker Container Boot (Horizon + Scheduler)
if [ "$DEPLOYMENT_TYPE" = "worker" ]; then
    echo "=> Preparing Horizon Worker Node..."
    php artisan config:clear
    php artisan config:cache
    php artisan horizon:purge || true
    echo "=> Starting Supervisor (Horizon + Scheduler)..."
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord-worker.conf
fi

# 3. Web Container Boot (Migrations, Cache, Permissions)
echo "=> Running Migrations & Permission Sync..."
php artisan migrate --force
php artisan permissions:sync --guard=staff || true
php artisan permissions:sync --guard=user || true

echo "=> Warming caches..."
php artisan config:clear
php artisan cache:clear || true
php artisan config:cache
php artisan route:cache
php artisan storage:link --force || true

# 4. Start Web Server (FrankenPHP Safe Mode - fresh PHP state per request)
echo "=> Starting FrankenPHP in Standard Safe Mode (Fresh PHP state per request)..."
exec frankenphp run --config /etc/caddy/Caddyfile
