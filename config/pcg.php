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

];
