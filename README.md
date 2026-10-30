# PCG_CORE — MEPCOM HRMIS

Laravel 12 application (PHP 8.2+) for the PCG Maritime Environmental Protection Command (personnel registration, SOI profile, document review, 201 files, training/LMS, OSAERT).

- Setup on Windows: [docs/DEVELOPMENT_SETUP.md](docs/DEVELOPMENT_SETUP.md)
- Private remote access (Tailscale): [docs/DEPLOYMENT_GUIDE.md](docs/DEPLOYMENT_GUIDE.md)
- Security status: [docs/SECURITY_AUDIT.md](docs/SECURITY_AUDIT.md)
- Architecture / database / features: [docs/](docs/)
- Progress: [docs/IMPLEMENTATION_ROADMAP.md](docs/IMPLEMENTATION_ROADMAP.md)

```bash
composer install
cp .env.example .env   # then edit DB_* and ADMIN_*
php artisan key:generate
php artisan pcg:check
php artisan serve --host=127.0.0.1
```

`php artisan migrate` only adds new tables and indexes (back up first; see docs/DATABASE_ARCHITECTURE.md §4b). Never run `migrate:fresh/refresh/reset`. PHP 8.5 upgrade: docs/PHP_UPGRADE_WINDOWS.md.
