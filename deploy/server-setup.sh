#!/usr/bin/env bash
# =============================================================================
# MEPCOM HRMIS - one-time setup on Hermes (Rocky Linux 9), as root:
#     sudo bash ~/mepcom-hrmis-deploy/preflight.sh       (step 0, read-only)
#     sudo bash ~/mepcom-hrmis-deploy/server-setup.sh    (this script)
# Options: PORT=8010 (127.0.0.1 only)  PUBLIC=1 (Tailscale Funnel; default is
# Serve = tailnet only)  DEPLOY_USER=coastguard-admin
#
# Isolation guarantees (enforced below, see deploy/README.md):
#   - creates only things named mepcom / mepcom-hrmis / mepcom_hrmis;
#   - installs and upgrades NO packages; never edits php.ini, nginx.conf,
#     other conf.d / php-fpm.d files, firewalld, sshd or other apps' folders;
#   - refuses to overwrite a file that this kit did not create;
#   - tests every config before a graceful reload (never a restart); a config
#     that fails its test is removed again, so nothing is reloaded broken;
#   - its database user can reach only its own database, with a connection cap;
#   - backs up all databases and the nginx/php-fpm configs first;
#   - compares the other apps before and after (verify.sh).
# Safe to run again: existing .env, database and Tailscale login are kept.
# =============================================================================
set -Eeuo pipefail
umask 022
export PATH=/usr/local/sbin:/usr/local/bin:$PATH   # composer lives in /usr/local/bin
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$HERE/config.sh"; . "$HERE/lib.sh"
LOG=/var/log/mepcom-hrmis-setup-$(date +%Y%m%d-%H%M%S).log
exec > >(tee -a "$LOG") 2>&1
trap 'echo; echo "!! STOPPED at line $LINENO: $BASH_COMMAND"; echo "!! Log: $LOG"; echo "!! Nothing outside MEPCOM HRMIS was changed; see deploy/README.md (Undo) to remove its pieces."' ERR

TS=(tailscale --socket="$TS_SOCK")
ts_json() { "${TS[@]}" status --json 2>/dev/null | php -r "$1"; }

# install_ours <source> <target>: install a kit file; refuse to replace a foreign one
install_ours() {
    if [[ -e "$2" ]] && ! ours "$2"; then die "$2 exists and was not created by this kit - not touching it."; fi
    install -m 0644 "$1" "$2"
}

[[ $EUID -eq 0 ]] || die "run with sudo"

# ----------------------------------------------------------------- 0. checks
say "0. Preflight (read-only)"
bash "$HERE/preflight.sh" || die "preflight found problems (listed above). Nothing was changed."
[[ -f "$BASE/shared/PORT" ]] && PORT="$(cat "$BASE/shared/PORT")"   # a re-run keeps the original port

# ----------------------------------------------------------------- 1. safety backups
say "1. Safety backups of everything that already exists"
BK="/root/mepcom-hrmis-predeploy-$(date +%Y%m%d-%H%M%S)"
install -d -m 0700 "$BK"
mysqldump --all-databases --single-transaction --routines --events --triggers 2>/dev/null | gzip > "$BK/all-databases.sql.gz"
tar -czf "$BK/configs.tar.gz" /etc/nginx /etc/php-fpm.d /etc/php-fpm.conf /etc/php.ini /etc/sudoers.d 2>/dev/null || true
(cd "$BK" && sha256sum -- * > SHA256SUMS)
[[ "$(stat -c %s "$BK/all-databases.sql.gz")" -gt 1000 ]] || die "database backup looks empty ($BK)"
ok "backups in $BK (root-only)"

# ----------------------------------------------------------------- 2. account, folders, SELinux
say "2. Service account, folders, SELinux labels for this app only"
getent group "$APP_USER" >/dev/null || groupadd --system "$APP_USER"
id "$APP_USER" >/dev/null 2>&1 || useradd --system --gid "$APP_USER" --home-dir "/var/lib/$APP_USER" --create-home --shell /sbin/nologin "$APP_USER"
usermod -aG "$APP_USER" "$DEPLOY_USER"
usermod -aG "$APP_USER" nginx

install -d -o "$DEPLOY_USER" -g "$APP_USER" -m 2750 "$BASE" "$BASE/releases" "$BASE/shared"
install -d -o "$DEPLOY_USER" -g "$DEPLOY_USER" -m 0700 "$BASE/backups"
for d in storage storage/app storage/app/private storage/app/private/personnel-documents storage/app/public \
         storage/framework storage/framework/cache storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs uploads uploads/selfies uploads/documents_toupload \
         uploads/201_files uploads/disciplinary_actions; do
    install -d -o "$APP_USER" -g "$APP_USER" -m 2770 "$BASE/shared/$d"
done
setfacl -R -m "g:$APP_USER:rwX" -m "d:g:$APP_USER:rwX" "$BASE/shared/storage" "$BASE/shared/uploads"
# file encryption key ring: written by the deploy account, read (not written) by the app's group
install -d -o "$DEPLOY_USER" -g "$APP_USER" -m 2750 "$BASE/shared/keys"
[[ -d "$BASE/repo.git" ]] || sudo -u "$DEPLOY_USER" git init --bare --quiet "$BASE/repo.git"
echo "$PORT" > "$BASE/shared/PORT"

add_fc() { semanage fcontext -a "$@" 2>/dev/null || semanage fcontext -m "$@"; }
add_fc -t httpd_sys_content_t "$BASE(/.*)?"
add_fc -t httpd_sys_rw_content_t "$BASE/shared/storage(/.*)?"
add_fc -t httpd_sys_rw_content_t "$BASE/shared/uploads(/.*)?"
add_fc -t httpd_sys_content_t "$BASE/shared/keys(/.*)?"
restorecon -R "$BASE"
semanage port -l | grep -E '^http_port_t ' | grep -qw "$PORT" || semanage port -a -t http_port_t -p tcp "$PORT"
ok "$BASE ready; SELinux labels for $BASE and port $PORT only"

# ----------------------------------------------------------------- 3. database + .env
say "3. Database ${DB_NAME} (own user, own database only) and .env"
ENV_FILE="$BASE/shared/.env"
if [[ -e "$ENV_FILE" ]]; then
    ok "$ENV_FILE exists - left untouched"
else
    mysql -NBe "SHOW DATABASES LIKE '${DB_NAME}'" | grep -q . && die "database ${DB_NAME} exists but there is no .env - stopping so nothing is overwritten"
    db_pass="$(openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c 40)"
    app_key="base64:$(openssl rand -base64 32)"
    mysql <<SQL
CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${db_pass}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${db_pass}' WITH MAX_USER_CONNECTIONS ${DB_MAX_CONNECTIONS};
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
    sed -e "s|@APP_KEY@|${app_key}|" -e "s|@DB_PASSWORD@|${db_pass}|" -e "s|@DB_NAME@|${DB_NAME}|" \
        -e "s|@DB_USER@|${DB_USER}|" -e "s|@BASE@|${BASE}|g" -e "s|@APP_URL@|https://${TS_HOSTNAME}.invalid|" \
        "$HERE/env.production.template" > "$ENV_FILE"
    unset db_pass app_key
    chown "$DEPLOY_USER:$APP_USER" "$ENV_FILE"; chmod 0640 "$ENV_FILE"; restorecon "$ENV_FILE"
    ok "created database ${DB_NAME}, user ${DB_USER}@localhost (max ${DB_MAX_CONNECTIONS} connections) and $ENV_FILE"
fi

# ----------------------------------------------------------------- 4. PHP-FPM pool
say "4. PHP-FPM pool ${FPM_POOL} (runs as ${APP_USER}; shared php.ini untouched)"
install_ours "$HERE/php-fpm/mepcom-hrmis.conf" "$FPM_CONF"
if ! php-fpm -t >/dev/null 2>&1; then
    rm -f "$FPM_CONF"; php-fpm -t || true
    die "php-fpm config test failed; the new pool file was removed and php-fpm was NOT reloaded"
fi
systemctl reload php-fpm
ok "pool ${FPM_POOL} added (graceful reload; other pools keep serving)"

# ----------------------------------------------------------------- 5. nginx site
say "5. nginx site on 127.0.0.1:${PORT} (own file; nginx.conf and other sites untouched)"
tmp="$(mktemp)"; sed -e "s|@PORT@|${PORT}|g" -e "s|@BASE@|${BASE}|g" "$HERE/nginx/mepcom-hrmis.conf.template" > "$tmp"
install_ours "$tmp" "$NGINX_CONF"; rm -f "$tmp"
if ! nginx -t >/dev/null 2>&1; then
    rm -f "$NGINX_CONF"; nginx -t || true
    die "nginx config test failed; the new site file was removed and nginx was NOT reloaded"
fi
systemctl reload nginx
ok "site added (graceful reload; other sites keep serving)"

# ----------------------------------------------------------------- 6. sudo for releases
say "6. Deploy account may reload PHP-FPM after a release (nothing else)"
if [[ ! -e "$SUDOERS" ]] || ours "$SUDOERS"; then
    printf '# %s\n%s ALL=(root) NOPASSWD: /usr/bin/systemctl reload php-fpm.service\n' "$MARKER" "$DEPLOY_USER" > "$SUDOERS.new"
    chmod 0440 "$SUDOERS.new"
    visudo -cf "$SUDOERS.new" >/dev/null && mv "$SUDOERS.new" "$SUDOERS" || { rm -f "$SUDOERS.new"; die "sudoers check failed"; }
    ok "$SUDOERS"
else
    die "$SUDOERS exists and was not created by this kit"
fi

# ----------------------------------------------------------------- 7. daily backup
say "7. Daily backup of ${DB_NAME} and its uploads (14 days)"
printf '# %s\n15 2 * * * %s bash %s/backup.sh >/dev/null 2>&1\n' "$MARKER" "$DEPLOY_USER" "/home/$DEPLOY_USER/mepcom-hrmis-deploy" > "$CRON_FILE.new"
if [[ -e "$CRON_FILE" ]] && ! ours "$CRON_FILE"; then rm -f "$CRON_FILE.new"; die "$CRON_FILE exists and was not created by this kit"; fi
mv "$CRON_FILE.new" "$CRON_FILE"; chmod 0644 "$CRON_FILE"
ok "$CRON_FILE (02:15 daily, into $BASE/backups)"

# ----------------------------------------------------------------- 8. own Tailscale node
say "8. Tailscale node ${TS_HOSTNAME} (separate; existing nodes untouched)"
install_ours "$HERE/systemd/tailscaled-mepcom.service" "/etc/systemd/system/$TS_UNIT"
systemctl daemon-reload
systemctl enable --now "$TS_UNIT"
for _ in $(seq 1 20); do [[ -S "$TS_SOCK" ]] && break; sleep 1; done
state="$(ts_json 'echo json_decode(stream_get_contents(STDIN))->BackendState ?? "";')"
if [[ "$state" != "Running" ]]; then
    echo; echo ">>> Open the login link below and sign in to the SAME Tailscale account as the other nodes."; echo
    "${TS[@]}" up --hostname="$TS_HOSTNAME" --operator="$DEPLOY_USER"
else
    "${TS[@]}" set --operator="$DEPLOY_USER"
fi
if [[ "${PUBLIC:-0}" == "1" ]]; then
    "${TS[@]}" funnel --bg --https=443 "http://127.0.0.1:$PORT"; mode="PUBLIC (Funnel)"
else
    "${TS[@]}" serve --bg --https=443 "http://127.0.0.1:$PORT"; mode="private (tailnet only)"
fi
dns="$(ts_json 'echo rtrim(json_decode(stream_get_contents(STDIN))->Self->DNSName ?? "", ".");')"
[[ -n "$dns" ]] || die "could not read the ${TS_HOSTNAME} node's DNS name"
sed -i "s|^APP_URL=.*|APP_URL=https://${dns}|" "$ENV_FILE"
ok "https://${dns} - ${mode}"

# ----------------------------------------------------------------- 9. compare
say "9. Other apps: before vs now"
bash "$HERE/verify.sh" || true   # the app itself answers only after the first release

say "Setup complete"
echo "  Address:  https://${dns} (${mode})"
echo "  Log:      $LOG"
echo "  Backups:  $BK"
echo "  Next (see deploy/README.md):"
echo "    1. optional: import your data into the EMPTY database:  sudo bash ~/mepcom-hrmis-deploy/import-database.sh ~/pcg_core.sql"
echo "    2. from the PC:  powershell -ExecutionPolicy Bypass -File deploy\\deploy.ps1 -Migrate"
echo "    3. on the server: cd $BASE/current && php artisan pcg:create-admin"
