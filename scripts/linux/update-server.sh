#!/usr/bin/env bash
# PCG_CORE - update an installed server to the latest code on GitHub.
#   sudo bash /var/www/pcg_core/scripts/linux/update-server.sh
# Backs up the database first, then: pull, composer install, additive
# migrations, rebuild caches, reload PHP-FPM. Never deletes data.
set -euo pipefail
APP_DIR="${APP_DIR:-/var/www/pcg_core}"
BRANCH="${BRANCH:-main}"
WEB_USER="www-data"
[ "$(id -u)" -eq 0 ] || { echo "Run with sudo."; exit 1; }
cd "$APP_DIR"

DB_NAME="$(grep -E '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '"')"
umask 077   # backups contain personnel data: root-only
mkdir -p /var/backups/pcg_core && chmod 700 /var/backups/pcg_core
BACKUP="/var/backups/pcg_core/${DB_NAME}-before-update-$(date +%Y%m%d-%H%M%S).sql.gz"
mysqldump --single-transaction --routines "$DB_NAME" | gzip > "$BACKUP"
echo "Backup: $BACKUP"

git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true
git fetch -q origin "$BRANCH"
git merge --ff-only "origin/${BRANCH}"
COMPOSER_ALLOW_SUPERUSER=1 composer install -q --no-dev --optimize-autoloader --no-interaction
chown -R root:"$WEB_USER" "$APP_DIR"; chown -R "$WEB_USER":"$WEB_USER" storage bootstrap/cache

runuser -u "$WEB_USER" -- php artisan down --retry=30 || true
runuser -u "$WEB_USER" -- php artisan migrate --force --no-interaction
runuser -u "$WEB_USER" -- php artisan optimize:clear -q
runuser -u "$WEB_USER" -- php artisan config:cache -q
runuser -u "$WEB_USER" -- php artisan route:cache -q
runuser -u "$WEB_USER" -- php artisan view:cache -q
runuser -u "$WEB_USER" -- php artisan up

FPM="$(ls /etc/init.d 2>/dev/null | grep -E '^php[0-9.]+-fpm$' | head -1)"
if command -v systemctl >/dev/null && [ -d /run/systemd/system ]; then systemctl reload "$FPM"; else service "$FPM" reload; fi
echo "Updated to $(git log -1 --format='%h %s')"
