#!/usr/bin/env bash
# MEPCOM HRMIS - step 0: READ-ONLY check of the Hermes server before setup.
#     sudo bash ~/mepcom-hrmis-deploy/preflight.sh            (root sees databases too)
#     PORT=8012 sudo bash ~/mepcom-hrmis-deploy/preflight.sh  (try another port)
#
# Changes nothing on the server except writing a snapshot file under
# /var/tmp/mepcom-hrmis-snapshots (states of the other apps, used by verify.sh).
# Exit code 0 = safe to run server-setup.sh with these names and this port.
set -Euo pipefail
export PATH=/usr/local/sbin:/usr/local/bin:$PATH   # sudo drops /usr/local/bin; composer lives there
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$HERE/config.sh"; . "$HERE/lib.sh"
[[ -f "$BASE/shared/PORT" ]] && PORT="$(cat "$BASE/shared/PORT")"   # a previous setup chose the port
problems=0
fail() { bad "$*"; problems=$((problems + 1)); }

say "System"
. /etc/os-release; echo "  ${PRETTY_NAME}"
grep -q 'PLATFORM_ID="platform:el9"' /etc/os-release && ok "EL9 (Rocky/RHEL 9) as expected" || fail "not an EL9 system; this kit is written for Hermes (Rocky Linux 9)"
df -h /srv 2>/dev/null | awk 'NR==2 {print "  disk /srv: " $4 " free of " $2}'

say "Shared software (checked, never installed or upgraded by this kit)"
for cmd in php php-fpm nginx mysql composer git tailscale tailscaled semanage setfacl curl ss; do
    have "$cmd" && ok "$cmd" || fail "missing: $cmd"
done
if have php; then
    php -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);' && ok "PHP $(php -r 'echo PHP_VERSION;') (8.2+ needed)" || fail "PHP $(php -r 'echo PHP_VERSION;') is older than 8.2"
    mods="$(php -m | tr 'A-Z' 'a-z')"
    missing=()
    for e in "${REQUIRED_EXT[@]}"; do grep -qx "$e" <<<"$mods" || missing+=("$e"); done
    if ((${#missing[@]})); then
        fail "PHP extensions missing: ${missing[*]} - installing them changes the shared PHP; ask the server owner (e.g. dnf install php-${missing[0]})"
    else
        ok "all required PHP extensions present"
    fi
fi

say "Names this kit will create (must be free)"
id "$APP_USER" >/dev/null 2>&1 && warn "user $APP_USER exists (fine only if a previous run of this kit made it)" || ok "user $APP_USER is free"
[[ -e "$BASE" ]] && warn "$BASE exists (fine only if a previous run of this kit made it)" || ok "$BASE is free"
for f in "$NGINX_CONF" "$FPM_CONF" "$SUDOERS" "/etc/systemd/system/$TS_UNIT" "$CRON_FILE"; do
    if [[ -e "$f" ]]; then ours "$f" && ok "$f exists and is ours" || fail "$f exists and is NOT from this kit"; else ok "$f is free"; fi
done
[[ -e "$FPM_SOCK" ]] && ! ours "$FPM_CONF" && fail "socket $FPM_SOCK is used by something else" || true
id "$DEPLOY_USER" >/dev/null 2>&1 && ok "deploy account $DEPLOY_USER exists" || fail "deploy account $DEPLOY_USER does not exist (set DEPLOY_USER=...)"

say "Port $PORT (127.0.0.1)"
if port_in_use "$PORT"; then
    ours "$NGINX_CONF" && grep -q "listen 127.0.0.1:$PORT;" "$NGINX_CONF" && ok "port $PORT is ours already" || fail "port $PORT is in use; pick a free one with PORT=..."
else
    ok "port $PORT is free"
fi
echo "  ports in use now:"; listeners | sed 's/^/    /'

say "What is running (left untouched)"
for f in /etc/nginx/conf.d/*.conf; do [[ -f "$f" ]] && echo "  nginx site:   $f"; done
for f in /etc/php-fpm.d/*.conf; do [[ -f "$f" ]] && echo "  php-fpm pool: $f"; done
systemctl list-units 'tailscaled*' --no-legend 2>/dev/null | awk '{print "  tailscale:    " $1 " " $3}'
ls -d /srv/*/ 2>/dev/null | sed 's/^/  app folder:   /'
if [[ $EUID -eq 0 ]] && have mysql; then
    mysql -NBe 'SHOW DATABASES' 2>/dev/null | grep -vxE 'information_schema|performance_schema|mysql|sys' | sed 's/^/  database:     /'
    mysql -NBe "SHOW DATABASES LIKE '${DB_NAME}'" | grep -q . && warn "database ${DB_NAME} exists (fine only if a previous run of this kit made it)" || ok "database ${DB_NAME} is free"
else
    warn "run with sudo to list databases"
fi

say "Snapshot of the other apps (compared after setup by verify.sh)"
file="$(take_snapshot before)"
sed 's/^/  /' "$file"
echo "  saved: $file"

say "Result"
if ((problems)); then
    echo "  $problems problem(s) above. Fix them before running server-setup.sh. Nothing was changed."
    exit 1
fi
echo "  All clear. Next: sudo PORT=$PORT bash ~/mepcom-hrmis-deploy/server-setup.sh"
