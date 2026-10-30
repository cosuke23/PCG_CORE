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

    // Reverse proxies to trust (comma-separated). Tailscale Serve: 127.0.0.1
    'trusted_proxies' => env('TRUSTED_PROXIES'),

];
