#!/usr/bin/env bash
# Back up MEPCOM HRMIS: its database and its private files (uploads, 201
# documents) into $BASE/backups/<timestamp>, kept for 14 days. Only this
# app's data. Run as the deploy account (cron does it daily; deploy.ps1
# -Migrate does it before migrations).
set -Eeuo pipefail
umask 077
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$HERE/config.sh"
OUT=$BASE/backups
KEEP_DAYS=${KEEP_DAYS:-14}
dir="$OUT/$(date +%Y%m%d-%H%M%S)"
mkdir -p "$dir"

env_get() { grep -E "^$1=" "$BASE/shared/.env" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }

cnf="$(mktemp)"
trap 'rm -f "$cnf"' EXIT
printf '[client]\nuser=%s\npassword=%s\nsocket=%s\n' \
    "$(env_get DB_USERNAME)" "$(env_get DB_PASSWORD)" "$(env_get DB_SOCKET)" > "$cnf"

mysqldump --defaults-extra-file="$cnf" --single-transaction --routines --triggers --no-tablespaces \
    --hex-blob --default-character-set=utf8mb4 "$(env_get DB_DATABASE)" | gzip > "$dir/database.sql.gz"
tar -C "$BASE/shared" -czf "$dir/files.tar.gz" uploads storage/app
# The file encryption keys are NOT in this backup on purpose (they are kept
# apart from the files). Record which key ids the files need, never the keys.
(cd "$BASE/current" && php artisan pcg:file-keys status --no-ansi > "$dir/file-key-ids.txt" 2>&1) || true
(cd "$dir" && sha256sum -- * > SHA256SUMS)

find "$OUT" -mindepth 1 -maxdepth 1 -type d -mtime +"$KEEP_DAYS" -exec rm -rf {} +
echo "backup ok: $dir ($(du -sh "$dir" | cut -f1))"
