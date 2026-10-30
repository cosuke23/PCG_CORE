# Browser end-to-end checks (development database only)

These scripts drive the real UI in Chromium against a **`pcg_core_dev`**
database filled by `php artisan pcg:dev-seed`. They change data, so never
point them at the real `pcg_core`.

Setup (once): Node.js 18+, then `npm install --no-save playwright@1 && npx playwright install chromium`.

```bash
# fresh dev database
mariadb -uroot -e "DROP DATABASE IF EXISTS pcg_core_dev; CREATE DATABASE pcg_core_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mariadb -uroot pcg_core_dev < database/dev/schema_inferred.sql
php artisan migrate --force   # admin accounts, audit log, contact messages, indexes
php artisan pcg:dev-seed
php artisan pcg:personnel-sync --apply --assign-to=MEPCOM   # personnel master for the HRMIS pages
php artisan serve --host=127.0.0.1 --port=8123

node tests/e2e/crawl.js /tmp            # every page, every role: HTTP, PHP and JS errors
node tests/e2e/flows-admin.js           # approve/reject, unit accounts, courses, students, CSV, OSAERT
node tests/e2e/flows-users.js           # unit review, disciplinary upload, uploads, SOI save, training apply
node tests/e2e/flow-register.js         # self-registration with a fake camera
node tests/e2e/flow-hrmis.js            # registration -> approval -> 201 upload/verify -> promotion -> reports, scope, auditor
```

`.env` for the run: `APP_ENV=local`, `DB_DATABASE=pcg_core_dev`. The seeder
creates admin@ / hr@ / lms@demo.test (password `Admin@Demo12345`) with two-factor
enrolled on a fixed dev-only key; `lib.js` computes the codes. Admin sign-in is
limited to 5 attempts/min per email, so wait a minute between repeated runs.
Environment variables: `PCG_E2E_URL` (default `http://127.0.0.1:8123`),
`PCG_E2E_DB_CLI` (default `mariadb -uroot`; e.g. `C:/xampp/mysql/bin/mysql -uroot`).
Re-seed before re-running the flow scripts: they expect the seeded state.
