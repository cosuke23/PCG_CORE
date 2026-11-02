#!/usr/bin/env bash
# Update ONLY MEPCOM HRMIS's own nginx site and PHP-FPM pool from this kit
# (security hardening, new limits) without re-running the whole setup.
#     sudo bash ~/mepcom-hrmis-deploy/update-web-config.sh
# Each file is tested before a graceful reload. If a test fails, the previous
# file is put back and nothing is reloaded, so the site keeps running as it was.
# Other sites, pools, nginx.conf and php.ini are never touched.
set -Eeuo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$HERE/config.sh"; . "$HERE/lib.sh"
[[ $EUID -eq 0 ]] || die "run with sudo"
[[ -f "$BASE/shared/PORT" ]] && PORT="$(cat "$BASE/shared/PORT")"
ours "$NGINX_CONF" || die "$NGINX_CONF is missing or not created by this kit; run server-setup.sh first"
ours "$FPM_CONF"   || die "$FPM_CONF is missing or not created by this kit; run server-setup.sh first"

stamp="$(date +%Y%m%d-%H%M%S)"
keep="/root/mepcom-hrmis-webconfig-$stamp"
install -d -m 0700 "$keep"
cp -p "$NGINX_CONF" "$FPM_CONF" "$keep/"
ok "previous files saved in $keep"

# swap_in <new file> <target> <test command> <service>
swap_in() {
    local new="$1" target="$2" test="$3" service="$4" old
    old="$keep/$(basename "$target")"
    if cmp -s "$new" "$target"; then ok "$target unchanged"; return 0; fi
    install -m 0644 "$new" "$target"
    restorecon "$target" 2>/dev/null || true
    if ! $test >/dev/null 2>&1; then
        install -m 0644 "$old" "$target"; restorecon "$target" 2>/dev/null || true
        $test || true
        die "$test failed with the new $target; the previous file is back and $service was NOT reloaded"
    fi
    systemctl reload "$service"
    ok "$target updated ($service reloaded gracefully)"
}

say "PHP-FPM pool ${FPM_POOL}"
swap_in "$HERE/php-fpm/mepcom-hrmis.conf" "$FPM_CONF" "php-fpm -t" php-fpm

say "nginx site on 127.0.0.1:${PORT}"
tmp="$(mktemp)"; trap 'rm -f "$tmp"' EXIT
sed -e "s|@PORT@|${PORT}|g" -e "s|@BASE@|${BASE}|g" "$HERE/nginx/mepcom-hrmis.conf.template" > "$tmp"
swap_in "$tmp" "$NGINX_CONF" "nginx -t" nginx

say "Check"
sleep 1
for path in / /login /admin/login; do
    code="$(curl -s -o /dev/null -m 30 -w '%{http_code}' "http://127.0.0.1:$PORT$path" || echo ERR)"
    [[ "$code" == "200" ]] && ok "$path HTTP $code" || bad "$path HTTP $code"
done
echo
echo "Done. Compare the other apps with:  sudo bash $HERE/verify.sh"
echo "Undo:  sudo cp $keep/$(basename "$NGINX_CONF") $NGINX_CONF && sudo cp $keep/$(basename "$FPM_CONF") $FPM_CONF && sudo nginx -t && sudo systemctl reload nginx php-fpm"
