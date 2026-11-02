#!/usr/bin/env bash
# =============================================================================
# PCG_CORE (MEPCOM HRMIS) - one-time server setup on Ubuntu / Debian.
#
#   sudo bash install-server.sh
#
# Installs nginx + PHP-FPM + MariaDB + Composer, puts the app in $APP_DIR,
# creates the database and its own database user, writes .env, runs the
# (additive) migrations and serves the app on 127.0.0.1:$PORT. Tailscale then
# makes it reachable on your tailnet (Serve, private) - or publicly with
# FUNNEL=1. Nothing is exposed on the server's public IP.
#
# Safe to run again: it never deletes data, never drops tables and never
# imports a dump into a database that already has tables.
#
# Options (environment variables):
#   APP_DIR=/var/www/pcg_core   where the app lives
#   REPO_URL=https://github.com/cosuke23/PCG_CORE.git   (or a local path)
#   BRANCH=main
#   PORT=8080                   local port nginx listens on (127.0.0.1 only)
#   DB_NAME=pcg_core  DB_USER=pcg_app
#   SQL_DUMP=/path/pcg_core.sql import this backup, ONLY if the database is empty
#   APP_KEY=base64:...          reuse the APP_KEY from the old PC's .env
#   FUNNEL=1                    public internet access via Tailscale Funnel
#   SKIP_TAILSCALE=1            do not install/configure Tailscale
# =============================================================================
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/pcg_core}"
REPO_URL="${REPO_URL:-https://github.com/cosuke23/PCG_CORE.git}"
BRANCH="${BRANCH:-main}"
PORT="${PORT:-8080}"
DB_NAME="${DB_NAME:-pcg_core}"
DB_USER="${DB_USER:-pcg_app}"
WEB_USER="www-data"

say()  { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
ok()   { printf '    \033[32m[ OK ]\033[0m %s\n' "$*"; }
warn() { printf '    \033[33m[WARN]\033[0m %s\n' "$*"; }
die()  { printf '\n\033[31mERROR: %s\033[0m\n' "$*" >&2; exit 1; }
svc()  { # start/restart/reload a service with or without systemd
    if command -v systemctl >/dev/null 2>&1 && [ -d /run/systemd/system ]; then systemctl "$1" "$2"; else service "$2" "$1"; fi
}

[ "$(id -u)" -eq 0 ] || die "Run with sudo: sudo bash $0"
command -v apt-get >/dev/null || die "This script supports Ubuntu/Debian (apt) only."
[[ "$DB_NAME" =~ ^[A-Za-z0-9_]+$ && "$DB_USER" =~ ^[A-Za-z0-9_]+$ ]] || die "DB_NAME/DB_USER: letters, digits and _ only."
[[ "$PORT" =~ ^[0-9]+$ ]] || die "PORT must be a number."
. /etc/os-release
echo "PCG_CORE server setup on ${PRETTY_NAME}"

# ----------------------------------------------------------------- packages
say "Installing system packages"
export DEBIAN_FRONTEND=noninteractive
NGINX_PREINSTALLED=0; command -v nginx >/dev/null && NGINX_PREINSTALLED=1
apt-get update -qq
apt-get install -y -qq ca-certificates curl git unzip openssl nginx mariadb-server >/dev/null
# A freshly installed nginx ships a default site on port 80; drop it so it cannot clash with
# anything already on this server. An nginx that was here before is left exactly as it was.
if [ "$NGINX_PREINSTALLED" = "0" ] && [ -L /etc/nginx/sites-enabled/default ]; then rm -f /etc/nginx/sites-enabled/default; fi

# PHP >= 8.2 is required (Laravel 12). Use the distro PHP when new enough.
PHPV="$(apt-cache policy php-fpm 2>/dev/null | awk '/Candidate:/ {print $2}' | sed -E 's/^[0-9]+://; s/^([0-9]+\.[0-9]+).*/\1/')"
if [ -z "$PHPV" ] || [ "$(printf '%s\n8.2\n' "$PHPV" | sort -V | head -1)" != "8.2" ]; then
    say "Distro PHP ($PHPV) is older than 8.2 - adding the maintained PHP repository"
    if [ "${ID}" = "ubuntu" ]; then
        apt-get install -y -qq software-properties-common >/dev/null
        add-apt-repository -y ppa:ondrej/php >/dev/null
    else
        curl -fsSL https://packages.sury.org/php/apt.gpg -o /usr/share/keyrings/sury-php.gpg
        echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ ${VERSION_CODENAME} main" > /etc/apt/sources.list.d/sury-php.list
    fi
    apt-get update -qq
    PHPV="8.3"
fi
apt-get install -y -qq "php${PHPV}-fpm" "php${PHPV}-cli" "php${PHPV}-mysql" "php${PHPV}-mbstring" "php${PHPV}-xml" \
    "php${PHPV}-curl" "php${PHPV}-zip" "php${PHPV}-gd" "php${PHPV}-intl" "php${PHPV}-bcmath" "php${PHPV}-sqlite3" >/dev/null
ok "PHP $(php -r 'echo PHP_VERSION;') with the required extensions"

# upload limits (the app accepts 10 MB files, several per request)
for ini in "/etc/php/${PHPV}/fpm/php.ini" "/etc/php/${PHPV}/cli/php.ini"; do
    [ -f "$ini" ] || continue
    sed -i -E 's/^;?\s*upload_max_filesize\s*=.*/upload_max_filesize = 12M/; s/^;?\s*post_max_size\s*=.*/post_max_size = 120M/;
               s/^;?\s*max_input_vars\s*=.*/max_input_vars = 3000/; s/^;?\s*expose_php\s*=.*/expose_php = Off/;
               s~^;?\s*date.timezone\s*=.*~date.timezone = Asia/Manila~' "$ini"
done

if ! command -v composer >/dev/null; then
    say "Installing Composer"
    EXPECTED="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    [ "$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")" = "$EXPECTED" ] || die "Composer installer checksum mismatch."
    php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi
ok "Composer $(composer --version 2>/dev/null | awk '{print $3}')"

svc start mariadb >/dev/null 2>&1 || svc start mysql >/dev/null 2>&1 || true
svc restart "php${PHPV}-fpm" >/dev/null

# ----------------------------------------------------------------- code
say "Getting the application into ${APP_DIR}"
if [ -d "${APP_DIR}/.git" ]; then
    git -C "$APP_DIR" fetch -q origin "$BRANCH"
    git -C "$APP_DIR" checkout -q "$BRANCH"
    git -C "$APP_DIR" merge -q --ff-only "origin/${BRANCH}" || warn "Local changes in ${APP_DIR}; not updating the code."
else
    mkdir -p "$(dirname "$APP_DIR")"
    git clone -q --branch "$BRANCH" "$REPO_URL" "$APP_DIR" || die "git clone failed. If the GitHub repository is private, clone it yourself into ${APP_DIR} (e.g. with a deploy key) and run this script again."
fi
git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true
ok "Code at $(git -C "$APP_DIR" log -1 --format='%h %s' | cut -c1-70)"

cd "$APP_DIR"
COMPOSER_ALLOW_SUPERUSER=1 composer install -q --no-dev --optimize-autoloader --no-interaction
ok "PHP dependencies installed"

# ----------------------------------------------------------------- database
say "Database ${DB_NAME}"
mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

# keep an existing password from .env; otherwise create a random one
DB_PASS=""
if [ -f .env ]; then DB_PASS="$(grep -E '^DB_PASSWORD=' .env | head -1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/')"; fi
if [ -z "$DB_PASS" ] || [ "$DB_PASS" = "change-me" ]; then DB_PASS="$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 28)"; fi
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
          ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
          GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
          FLUSH PRIVILEGES;"
ok "Database user ${DB_USER} (localhost only)"

TABLES="$(mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}'")"
if [ -n "${SQL_DUMP:-}" ]; then
    [ -f "$SQL_DUMP" ] || die "SQL_DUMP file not found: $SQL_DUMP"
    if [ "$TABLES" -gt 0 ]; then
        warn "${DB_NAME} already has ${TABLES} tables - the backup was NOT imported (nothing overwritten)."
    else
        say "Importing ${SQL_DUMP}"
        # Windows PowerShell '>' writes UTF-16; convert so MariaDB can read it
        if head -c 2 "$SQL_DUMP" | od -An -tx1 | grep -qiE 'ff fe|fe ff'; then
            iconv -f UTF-16 -t UTF-8 "$SQL_DUMP" > /tmp/pcg_import.sql && SQL_DUMP=/tmp/pcg_import.sql
        fi
        mysql "$DB_NAME" < "$SQL_DUMP"
        rm -f /tmp/pcg_import.sql
        ok "Imported ($(mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}'") tables)"
    fi
elif [ "$TABLES" -eq 0 ]; then
    warn "${DB_NAME} is empty. To bring your data, rerun with SQL_DUMP=/path/to/backup.sql"
fi

# A migrations table copied from a broken Windows import may lack AUTO_INCREMENT: set it aside (renamed, not deleted).
if [ "$(mysql -N -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='${DB_NAME}' AND table_name='migrations' AND column_name='id' AND extra NOT LIKE '%auto_increment%'")" -gt 0 ]; then
    mysql -e "RENAME TABLE \`${DB_NAME}\`.migrations TO \`${DB_NAME}\`.migrations_old_backup_$(date +%Y%m%d%H%M%S)"
    warn "The old 'migrations' table had no AUTO_INCREMENT; renamed it aside so migrations can run."
fi

# ----------------------------------------------------------------- .env
say "Configuration (.env)"
[ -f .env ] || cp .env.example .env
setenv() { # setenv KEY VALUE  (adds or replaces one line)
    local v="$2"; [[ "$v" =~ [[:space:]\#\"] ]] && v="\"${v//\"/\\\"}\""
    local e="${v//\\/\\\\}"; e="${e//&/\\&}"; local d=$'\001'   # escape \ and & ; ^A as sed delimiter
    if grep -qE "^$1=" .env; then sed -i "s${d}^$1=.*${d}$1=${e}${d}" .env; else printf '%s=%s\n' "$1" "$v" >> .env; fi
}
setenv APP_ENV production
setenv APP_DEBUG false
setenv DB_CONNECTION mysql
setenv DB_HOST 127.0.0.1
setenv DB_PORT 3306
setenv DB_DATABASE "$DB_NAME"
setenv DB_USERNAME "$DB_USER"
setenv DB_PASSWORD "$DB_PASS"
setenv TRUSTED_PROXIES 127.0.0.1
setenv SESSION_SECURE_COOKIE true
DATA_DIR="${APP_DIR}/storage/app/private"
grep -qE '^PCG_UPLOAD_ROOT=.+' .env || setenv PCG_UPLOAD_ROOT "${DATA_DIR}/uploads"
grep -qE '^PCG_SELFIE_DIR=.+' .env || setenv PCG_SELFIE_DIR "${DATA_DIR}/uploads/selfies"
if [ -n "${APP_KEY:-}" ]; then setenv APP_KEY "$APP_KEY";
elif ! grep -qE '^APP_KEY=base64:.+' .env; then php artisan key:generate --force -q; warn "New APP_KEY generated. To keep data encrypted on the old PC readable, rerun with APP_KEY=<old key>."; fi
chmod 640 .env
ok ".env written (DB password stored only there)"

# ----------------------------------------------------------------- files + permissions
mkdir -p "${DATA_DIR}/uploads/selfies" storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R root:"$WEB_USER" "$APP_DIR"
chown -R "$WEB_USER":"$WEB_USER" storage bootstrap/cache
chmod -R u=rwX,g=rwX,o= storage bootstrap/cache
chown root:"$WEB_USER" .env
ok "Private storage: ${DATA_DIR} (copy uploads from the old PC here)"

# ----------------------------------------------------------------- migrate + cache
say "Migrations (additive only) and caches"
runuser -u "$WEB_USER" -- php artisan migrate --force --no-interaction
runuser -u "$WEB_USER" -- php artisan optimize:clear -q
runuser -u "$WEB_USER" -- php artisan config:cache -q
runuser -u "$WEB_USER" -- php artisan route:cache -q
runuser -u "$WEB_USER" -- php artisan view:cache -q
ok "Migrations done, caches built"

# ----------------------------------------------------------------- nginx
say "Web server (nginx on 127.0.0.1:${PORT})"
cat > /etc/nginx/sites-available/pcg_core <<NGINX
# PCG_CORE - local only; Tailscale Serve/Funnel publishes it.
server {
    listen 127.0.0.1:${PORT};
    server_name _;
    root ${APP_DIR}/public;
    index index.php;
    client_max_body_size 120m;
    server_tokens off;

    # never serve legacy upload folders or dotfiles directly
    location ~* ^/(documents_toupload|201_files|disciplinary_actions|selfies)(/|\$) { return 404; }
    location ~ /\.(?!well-known) { deny all; }

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHPV}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
    }
    location ~* \.(css|js|webp|png|jpg|jpeg|svg|woff2|ico)\$ { expires 7d; access_log off; try_files \$uri =404; }
}
NGINX
ln -sf /etc/nginx/sites-available/pcg_core /etc/nginx/sites-enabled/pcg_core
nginx -t -q
if pgrep -x nginx >/dev/null; then svc reload nginx >/dev/null; else svc start nginx >/dev/null; fi
sleep 1
CODE="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${PORT}/login" || true)"
[ "$CODE" = "200" ] && ok "App answers on http://127.0.0.1:${PORT}" || warn "http://127.0.0.1:${PORT}/login answered ${CODE}; see ${APP_DIR}/storage/logs/laravel.log"

# ----------------------------------------------------------------- daily backup
cat > /etc/cron.daily/pcg-core-backup <<CRON
#!/bin/sh
# PCG_CORE: daily database backup, 14 days kept (added by install-server.sh)
umask 077
mkdir -p /var/backups/pcg_core && chmod 700 /var/backups/pcg_core
mysqldump --single-transaction --routines ${DB_NAME} | gzip > /var/backups/pcg_core/${DB_NAME}-\$(date +%F).sql.gz
find /var/backups/pcg_core -name '*.sql.gz' -mtime +14 -delete
CRON
chmod 755 /etc/cron.daily/pcg-core-backup
ok "Daily database backup to /var/backups/pcg_core (14 days)"

# ----------------------------------------------------------------- tailscale
if [ "${SKIP_TAILSCALE:-0}" = "1" ]; then
    warn "Tailscale skipped (SKIP_TAILSCALE=1)."
else
    say "Tailscale"
    command -v tailscale >/dev/null || curl -fsSL https://tailscale.com/install.sh | sh
    if ! tailscale status >/dev/null 2>&1; then
        warn "This server is not signed in to Tailscale yet. Run:  sudo tailscale up   then run this script again."
    else
        if [ "${FUNNEL:-0}" = "1" ]; then
            tailscale funnel --bg "http://127.0.0.1:${PORT}" >/dev/null
            MODE="PUBLIC (Funnel)"
        else
            tailscale serve --bg "http://127.0.0.1:${PORT}" >/dev/null
            MODE="private (tailnet only)"
        fi
        HOST="$(tailscale status --json | php -r '$j=json_decode(stream_get_contents(STDIN),true); echo rtrim($j["Self"]["DNSName"]??"",".");')"
        if [ -n "$HOST" ]; then
            setenv APP_URL "https://${HOST}"
            runuser -u "$WEB_USER" -- php artisan config:cache -q
            ok "Published ${MODE}: https://${HOST}"
        fi
    fi
fi

say "Done"
cat <<EOF
  App folder:     ${APP_DIR}
  Local address:  http://127.0.0.1:${PORT}
  Uploads:        ${DATA_DIR}/uploads  (copy documents_toupload, 201_files, disciplinary_actions, selfies here)
  Next steps:
    - mail for sign-in codes: edit ${APP_DIR}/.env (MAIL_*), then: cd ${APP_DIR} && sudo -u ${WEB_USER} php artisan config:cache
    - first admin (if the database has none): cd ${APP_DIR} && sudo -u ${WEB_USER} php artisan pcg:create-admin
    - health check: cd ${APP_DIR} && sudo -u ${WEB_USER} php artisan pcg:check
    - later updates: sudo bash ${APP_DIR}/scripts/linux/update-server.sh
EOF
