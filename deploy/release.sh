#!/usr/bin/env bash
# Build a MEPCOM HRMIS release from $BASE/repo.git and switch to it.
# Run as the deploy account (no sudo password); deploy.ps1 on the PC does this:
#     bash ~/mepcom-hrmis-deploy/release.sh [git-ref]
# Pending migrations are listed and the release stops, unless MIGRATE=1 is set
# (deploy.ps1 -Migrate takes a backup first). If the new release does not
# answer, the previous one is switched back automatically.
# Roll back by hand:  bash ~/mepcom-hrmis-deploy/release.sh --rollback
set -Eeuo pipefail
umask 007   # group (mepcom) must read the code and write caches
export PATH=/usr/local/bin:$PATH   # composer lives in /usr/local/bin
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$HERE/config.sh"
[[ -f "$BASE/shared/PORT" ]] && PORT="$(cat "$BASE/shared/PORT")"
KEEP=5

reload_php() { [[ "${SKIP_RELOAD:-0}" == "1" ]] || sudo -n systemctl reload php-fpm.service; }
relabel()    { restorecon -R "$@" 2>/dev/null || true; }
switch_to()  { ln -sfn "$1" "$BASE/current.new" && mv -T "$BASE/current.new" "$BASE/current"; relabel "$BASE/current"; reload_php; }

if [[ "${1:-}" == "--rollback" ]]; then
    current="$(readlink -f "$BASE/current")"
    previous="$(ls -1d "$BASE"/releases/*/ | sed 's#/$##' | grep -vx "$current" | tail -1)"
    [[ -n "$previous" ]] || { echo "No previous release to roll back to."; exit 1; }
    switch_to "$previous"
    echo "Rolled back: $current -> $previous"
    echo "Database changes are not rolled back; restore a backup if a migration must be undone."
    exit 0
fi

REF="${1:-live}"
SHA="$(git --git-dir="$BASE/repo.git" rev-parse --short=8 "$REF^{commit}")"
REL="$BASE/releases/$(date +%Y%m%d-%H%M%S)-$SHA"
PREVIOUS=""; [[ -L "$BASE/current" ]] && PREVIOUS="$(readlink -f "$BASE/current")"   # empty on the first release

echo "==> Building $REF ($SHA) in $REL"
mkdir -p "$REL"
git --git-dir="$BASE/repo.git" archive "$REF" | tar -x -C "$REL"
echo "$SHA" > "$REL/REVISION"
rm -rf "$REL/storage"
ln -s "$BASE/shared/storage" "$REL/storage"
ln -s "$BASE/shared/.env" "$REL/.env"

cd "$REL"
echo "==> Composer"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress
# No build step: CSS and JS are committed in public/.

# Uploaded-file encryption settings, added once to an older .env
if ! grep -qE '^PCG_FILE_KEYS_PATH=' "$BASE/shared/.env"; then
    printf '\n# Uploaded-file encryption (docs/FILE_ENCRYPTION.md)\nPCG_FILE_KEYS_PATH=%s\nPCG_FILE_KEYS_MODE=0640\n' "$BASE/shared/keys/file-keys.json" >> "$BASE/shared/.env"
fi
# server-setup.sh creates it (owner deploy, group app, 2750); this covers an older server
[[ -d "$BASE/shared/keys" ]] || install -d -m 2750 "$BASE/shared/keys"

echo "==> Laravel caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Uploaded-file encryption: the key ring is created once. init refuses when
# encrypted files exist without a key ring (restore it from the key backup).
if ! php artisan pcg:file-keys status >/dev/null 2>&1; then
    echo "==> Creating the file encryption key ring"
    php artisan pcg:file-keys init
    echo "!!  Back up the new key ring OFF the server now (docs/FILE_ENCRYPTION.md, step 2)."
fi

status="$(php artisan migrate:status 2>&1 || true)"
pending="$(grep -c 'Pending' <<<"$status" || true)"
grep -qiE 'Migration table not found|migrations table' <<<"$status" && pending=all
if [[ "$pending" != "0" ]]; then
    echo "$status" | grep -E 'Pending|not found' || true
    if [[ "${MIGRATE:-0}" != "1" ]]; then
        echo "Stopping: $pending pending migration(s). Run deploy.ps1 -Migrate (it backs up first)."
        exit 1
    fi
    echo "==> Migrations (additive only)"
    php artisan migrate --force
fi

relabel "$REL"
echo "==> Switching current -> $REL"
switch_to "$REL"

echo "==> Checking the site"
sleep 1
for path in / /login; do
    code="$(curl -s -o /dev/null -m 30 -w '%{http_code}' "http://127.0.0.1:$PORT$path" || echo ERR)"
    echo "  $path HTTP $code"
    if [[ "$code" != "200" && -z "${SKIP_SITE_CHECK:-}" ]]; then
        if [[ -n "$PREVIOUS" && -d "$PREVIOUS" && "$PREVIOUS" != "$REL" ]]; then
            switch_to "$PREVIOUS"
            echo "RELEASE FAILED: $path answered $code. Switched back to $PREVIOUS. See $BASE/shared/storage/logs/."
        else
            echo "RELEASE FAILED: $path answered $code. See $BASE/shared/storage/logs/."
        fi
        exit 1
    fi
done

echo "==> Keeping the newest $KEEP releases"
ls -1d "$BASE"/releases/*/ | sed 's#/$##' | head -n -"$KEEP" | while read -r old; do
    [[ "$old" == "$(readlink -f "$BASE/current")" ]] || rm -rf "$old"
done

echo "RELEASE OK: $SHA"
