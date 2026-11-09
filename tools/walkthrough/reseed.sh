#!/usr/bin/env bash
# Fresh pcg_core_dev for a capture run (dev database only).
set -euo pipefail
cd "$(dirname "$0")/../.."
grep -q '^DB_DATABASE=pcg_core_dev' .env || { echo "DB_DATABASE must be pcg_core_dev"; exit 1; }
DB="${PCG_E2E_DB_CLI:-mariadb -uroot}"
$DB -e "DROP DATABASE IF EXISTS pcg_core_dev; CREATE DATABASE pcg_core_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
$DB pcg_core_dev < database/dev/schema_inferred.sql
php artisan migrate --force >/dev/null
php artisan db:seed --force >/dev/null
php artisan cache:clear >/dev/null
echo "pcg_core_dev re-seeded"
