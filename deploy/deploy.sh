#!/usr/bin/env bash
# Zero-surprise deploy for a single server. Run as the deploy user from the repo root:
#   ./deploy/deploy.sh
set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
BACKEND="$APP_DIR/backend"
FRONTEND="$APP_DIR/frontend"

echo "==> Pulling code"
git -C "$APP_DIR" pull --ff-only

echo "==> Backend dependencies"
cd "$BACKEND"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

php artisan down --retry=15 --refresh=15 || true
trap 'php artisan up' EXIT

echo "==> Database migrations"
php artisan migrate --force

echo "==> Caches"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link 2>/dev/null || true

echo "==> Frontend build"
cd "$FRONTEND"
npm ci --no-audit --no-fund --legacy-peer-deps
npm run build

echo "==> Restart workers"
cd "$BACKEND"
php artisan queue:restart
sudo -n systemctl reload php8.3-fpm 2>/dev/null || echo "   (reload php-fpm manually to reset OPcache)"

php artisan up
trap - EXIT
echo "==> Done. Smoke test: curl -fsS \"\$(grep ^APP_URL .env | cut -d= -f2)/up\""
