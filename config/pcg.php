<?php

/*
|--------------------------------------------------------------------------
| PCG_CORE (MEPCOM HRMIS) application settings
|--------------------------------------------------------------------------
|
| Values that used to be hardcoded in controllers. Set them in .env;
| never commit real values.
|
*/

return [

    // Super-admin login (/admin/login). ADMIN_PASSWORD_HASH must be a bcrypt
    // hash, generated with:  php artisan pcg:hash-password
    // Unit and M1 checkers: second sign-in step. 'email' = one-time code to the
    // checker's email (owner request 2026-11-20), 'off' = password only.
    // Monitoring and alerts (App\Support\Monitor). alert_to: comma-separated emails;
    // empty = the active super_admin accounts. backup_dir empty = ../../backups next to
    // the release (Hermes: /srv/mepcom-hrmis/backups).
    // Clearance expiry reminders (pcg:clearance-reminders): days before the valid-until
    // date on which the member is emailed (0 = on the day). PCG_CLEARANCE_REMINDER_DAYS=30,7,0
    // Data Privacy Act (pages/privacy, pcg:retention-report, docs/PRIVACY_RECORD.md).
    // Raise notice_version when the notice changes; registration stores it with the consent.
    'privacy' => [
        'notice_version' => env('PCG_PRIVACY_NOTICE_VERSION', '1.0'),
        'notice_date' => env('PCG_PRIVACY_NOTICE_DATE', '2026-11-20'),
        'dpo_name' => env('PCG_DPO_NAME', 'MEPCOM Data Protection Officer'),
        'dpo_email' => env('PCG_DPO_EMAIL', ''),
        'retention' => [
            'pending_registration_days' => (int) env('PCG_RETAIN_PENDING_DAYS', 180),
            'audit_days' => (int) env('LOG_AUDIT_DAYS', 400),
            'contact_message_days' => (int) env('PCG_RETAIN_CONTACT_DAYS', 365),
            'leave_days' => (int) env('PCG_RETAIN_LEAVE_DAYS', 1825),
        ],
    ],

    // Re-enlistment process (App\Support\ReenlistmentCases): the checklist HR keeps per member
    // (PCG_REENLIST_REQUIREMENTS, separated by |) and the reminder days before ETE.
    // Defaults until the PCG directive is supplied.
    'reenlistment' => [
        'requirements' => array_values(array_filter(array_map('trim', explode('|', (string) env('PCG_REENLIST_REQUIREMENTS',
            'Letter of intent to re-enlist|Recommendation of the commanding officer|Medical and dental clearance|Physical fitness test (PFT) result|Neuro-psychiatric evaluation|NBI clearance|Statement of assets, liabilities and net worth (SALN)|Updated SOI and 201 file'))))),
        'reminder_days' => array_values(array_filter(array_map('trim', explode(',', (string) env('PCG_REENLIST_REMINDER_DAYS', '180,90'))), 'is_numeric')),
    ],

    // Promotion eligibility (App\Support\PromotionEligibility): years in grade per classification,
    // and per rank code {"years": n, "courses": ["keyword", ...]} (courses matched in the training
    // records). Defaults until the PCG directive is supplied; PCG_PROMOTION_RANKS takes JSON.
    'promotion' => [
        'years' => [
            'non_officer' => (float) env('PCG_PROMOTION_YEARS_NON_OFFICER', 2),
            'officer' => (float) env('PCG_PROMOTION_YEARS_OFFICER', 3),
        ],
        'ranks' => json_decode((string) env('PCG_PROMOTION_RANKS', '{}'), true) ?: [],
    ],

    // Deployments / sea duty (App\Support\Deployments): RNR days suggested per 30 days
    // deployed when a deployment ends (0 = no suggestion; the checker types the days).
    'deployment' => [
        'rnr_days_per_30' => (float) env('PCG_RNR_DAYS_PER_30', 0),
    ],

    'clearance' => [
        'reminder_days' => array_values(array_filter(array_map('trim', explode(',', (string) env('PCG_CLEARANCE_REMINDER_DAYS', '30,7,0'))), 'is_numeric')),
    ],

    'monitor' => [
        'alert_to' => env('PCG_ALERT_EMAIL', ''),
        'error_alerts' => (bool) env('PCG_ERROR_ALERTS', true),
        'backup_dir' => env('PCG_BACKUP_DIR', ''),
        'backup_max_age_hours' => (int) env('PCG_BACKUP_MAX_AGE_HOURS', 30),
        'disk_min_free_percent' => (float) env('PCG_DISK_MIN_FREE_PERCENT', 10),
        // off-site copy and restore drill from the office PC (deploy/offsite): heartbeat files in shared/
        'shared_dir' => env('PCG_SHARED_DIR', ''),
        'offsite_expected' => (bool) env('PCG_OFFSITE_EXPECTED', false),
    ],

    'unit' => [
        'two_factor' => env('PCG_UNIT_2FA', 'email'),
        'email_code_minutes' => (int) env('PCG_UNIT_EMAIL_CODE_MINUTES', 10),
    ],

    'admin' => [
        'email' => env('ADMIN_EMAIL'),
        'password_hash' => env('ADMIN_PASSWORD_HASH'),

        // Second sign-in step for admin accounts: 'email' = one-time code sent
        // to the admin's email address (owner decision 2026-10-31); 'totp' =
        // authenticator app. Email needs working MAIL_* settings in .env.
        'two_factor' => env('PCG_ADMIN_2FA', 'email'),
        'email_code_minutes' => (int) env('PCG_ADMIN_EMAIL_CODE_MINUTES', 10),
        // Roles that sign in with the password only, without the emailed code
        // (owner decision 2026-11-03: the re-enlistment admin). Comma-separated;
        // super_admin is always refused. Empty value = every role uses the code.
        'password_only_roles' => array_values(array_filter(array_map('trim',
            explode(',', (string) env('PCG_ADMIN_PASSWORD_ONLY_ROLES', 'reenlistment_admin'))))),
        // An admin session ends this many hours after sign-in, busy or not.
        'max_session_hours' => (int) env('PCG_ADMIN_MAX_SESSION_HOURS', 12),
    ],

    // Folder that holds documents_toupload/, 201_files/ and
    // disciplinary_actions/. Files are served only through /files/{path}
    // (FileController), so this should be OUTSIDE the web root, e.g.
    // storage/app/private. Production (cPanel) used
    // /home/<account>/public_html/pcg-mepcom.com/public
    'upload_root' => env('PCG_UPLOAD_ROOT') ?: public_path(),

    // Folder for registration selfies (stored in users.selfie_image as
    // "selfies/<file>.png").
    'selfie_dir' => env('PCG_SELFIE_DIR') ?: public_path('selfies'),

    // Private storage for the 201 file repository (personnel_documents).
    // Never under public/; files are served only by PersonnelDocumentController.
    'document_root' => env('PCG_DOCUMENT_ROOT') ?: storage_path('app/private/personnel-documents'),

    // Uploaded-file encryption (AES-256-GCM, docs/FILE_ENCRYPTION.md).
    // Key ring with the master keys: OUTSIDE the upload folders, the web root
    // and the folders that are backed up with the uploads; mode 0600.
    // Production: /srv/mepcom-hrmis/shared/keys/file-keys.json
    'file_keys_path' => env('PCG_FILE_KEYS_PATH') ?: storage_path('app/keys/file-keys.json'),
    // Tests and CI only: one base64 32-byte key instead of a key ring file.
    'file_key' => env('PCG_FILE_KEY', ''),
    // File mode of the key ring: 0600 (only the owner) or, on a server where
    // the deploy account writes it and the web app's group reads it, 0640.
    'file_keys_mode' => env('PCG_FILE_KEYS_MODE', '0600'),

    // Where profile photos used to be stored (public disk); read until
    // `pcg:files encrypt --apply` moves them into the encrypted upload folder.
    'legacy_photo_dir' => env('PCG_LEGACY_PHOTO_DIR') ?: storage_path('app/public/profile_photos'),

    // Upload limit for 201 documents, in kilobytes.
    'document_max_kb' => (int) env('PCG_DOCUMENT_MAX_KB', 10240),

    // Reverse proxies to trust (comma-separated). Tailscale Serve: 127.0.0.1
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    // Extra host names the app answers to besides APP_URL's host, 127.0.0.1 and
    // localhost (comma-separated, exact). Production only (TrustHosts).
    'trusted_hosts' => env('PCG_TRUSTED_HOSTS', ''),

    // Content-Security-Policy: enforce (default) | report | off
    'csp' => env('PCG_CSP', 'enforce'),

    // PCG HRMIS Android app (mobile/android). Members whose SOI is submitted
    // download it at /mobile-app (MobileAppController). The APK lives outside
    // public/ and is served only after that check; the version is shown on the page.
    // Production: /srv/mepcom-hrmis/shared/storage/app/private/mobile/pcg-hrmis.apk
    'android_app' => [
        'apk_path' => env('PCG_ANDROID_APK') ?: storage_path('app/private/mobile/pcg-hrmis.apk'),
        'version' => env('PCG_ANDROID_APK_VERSION', ''),
    ],

    // Leave module (App\Support\LeaveTypes, LeaveCredits, LeaveCalendar). Defaults until the PCG
    // leave directive is supplied; set the directive's numbers here or in .env.
    'leave' => [
        // how a request's length is counted: 'calendar' (every day) or 'working' (Mon-Fri, minus holidays)
        'count' => env('PCG_LEAVE_COUNT', 'calendar'),
        // non-working days for 'working': YYYY-MM-DD, comma-separated in .env
        'holidays' => array_values(array_filter(array_map('trim', explode(',', (string) env('PCG_LEAVE_HOLIDAYS', ''))))),
        // ordinary leave credits added on the 1st of each month after the opening balance
        'monthly_credit' => (float) env('PCG_LEAVE_MONTHLY_CREDIT', 2.5),
        // days per calendar year of the yearly pools
        'per_year' => [
            'solo_parent' => (float) env('PCG_LEAVE_SOLO_PARENT_DAYS', 7),
            'vawc' => (float) env('PCG_LEAVE_VAWC_DAYS', 10),
        ],
        // how far back a request may start (late filing)
        'backdate_days' => (int) env('PCG_LEAVE_BACKDATE_DAYS', 30),
        // longest single request per type (days), e.g. ['rnr' => 30]; types not listed keep App\Support\LeaveTypes
        'max_days' => [],
        // remind unit checkers of requests waiting longer than this (days); 0 = never
        'reminder_days' => (int) env('PCG_LEAVE_REMINDER_DAYS', 2),
        // email members when their leave is decided
        'notify_members' => (bool) env('PCG_LEAVE_NOTIFY', true),
    ],

];
