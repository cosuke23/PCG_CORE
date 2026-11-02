#!/usr/bin/env bash
# Import your PCG_CORE data (a mysqldump from the PC) into the MEPCOM HRMIS
# database - ONLY if that database is still empty. Never touches another
# database: the target name is fixed (mepcom_hrmis) and the dump's own
# CREATE DATABASE / USE lines are stripped so it cannot write elsewhere.
#     sudo bash ~/mepcom-hrmis-deploy/import-database.sh ~/pcg_core.sql
# Then deploy with migrations (deploy.ps1 -Migrate) to add the HRMIS tables.
set -Eeuo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$HERE/config.sh"; . "$HERE/lib.sh"
[[ $EUID -eq 0 ]] || die "run with sudo"
DUMP="${1:-}"
[[ -f "$DUMP" ]] || die "usage: sudo bash $0 /path/to/pcg_core.sql"

mysql -NBe "SHOW DATABASES LIKE '${DB_NAME}'" | grep -q . || die "database ${DB_NAME} does not exist; run server-setup.sh first"
tables="$(mysql -NBe "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}'")"
[[ "$tables" -eq 0 ]] || die "${DB_NAME} already has ${tables} tables - nothing imported (nothing overwritten)"

work="$(mktemp -d)"; trap 'rm -rf "$work"' EXIT
src="$DUMP"
# Windows PowerShell '>' writes UTF-16; convert it.
if head -c 2 "$src" | od -An -tx1 | grep -qiE 'ff fe|fe ff'; then
    iconv -f UTF-16 -t UTF-8 "$src" > "$work/utf8.sql"; src="$work/utf8.sql"; ok "converted UTF-16 dump to UTF-8"
fi
# Keep the import inside mepcom_hrmis: drop database-switching statements.
grep -vE '^\s*(CREATE DATABASE|USE\s|DROP DATABASE)' "$src" | sed -E 's#^/\*!40000 DROP DATABASE.*##' > "$work/import.sql"
grep -qiE 'CREATE TABLE' "$work/import.sql" || die "the file has no CREATE TABLE statements - is it a mysqldump?"

say "Importing into ${DB_NAME}"
mysql "$DB_NAME" < "$work/import.sql"
ok "imported: $(mysql -NBe "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}'") tables"

# A migrations table copied from a broken Windows import may lack AUTO_INCREMENT: rename it aside (not deleted).
if [[ "$(mysql -NBe "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='${DB_NAME}' AND table_name='migrations' AND column_name='id' AND extra NOT LIKE '%auto_increment%'")" -gt 0 ]]; then
    mysql -e "RENAME TABLE \`${DB_NAME}\`.migrations TO \`${DB_NAME}\`.migrations_old_backup_$(date +%Y%m%d%H%M%S)"
    warn "the copied 'migrations' table had no AUTO_INCREMENT; renamed it aside so migrations can run"
fi
echo
echo "Next, from the PC:  powershell -ExecutionPolicy Bypass -File deploy\\deploy.ps1 -Migrate"
echo "Uploads: copy the PC's documents_toupload, 201_files, disciplinary_actions and selfies folders into"
echo "  $BASE/shared/uploads/   then:  sudo chown -R ${APP_USER}:${APP_USER} $BASE/shared/uploads && sudo restorecon -R $BASE/shared/uploads"
