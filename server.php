<?php

/**
 * Router for `php artisan serve` (Laravel uses base_path('server.php') when it
 * exists). The built-in PHP server ignores public/.htaccess, so without this
 * file the upload folders under public/ (documents_toupload, 201_files,
 * disciplinary_actions, selfies) could be downloaded directly, without
 * signing in. Matters most when the app is reachable from other devices
 * (Tailscale Serve/Funnel).
 *
 * Only these public folders/files are served directly; every other path goes
 * through Laravel, which serves uploads via the access-checked /files route
 * and returns 404 for anything else.
 */

$publicPath = getcwd();

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

$staticRoots = ['css', 'js', 'vendor', 'images', 'backgrounds', 'ph_json', 'storage', 'favicon.ico', 'robots.txt'];

$segments = preg_split('#[/\\\\]+#', trim($uri, "/\\"));
$isStatic = $uri !== '/'
    && in_array($segments[0], $staticRoots, true)   // exact, case-sensitive
    && !preg_grep('/^\.|^$/', $segments)              // no "..", ".env" or empty parts
    && strpbrk($uri, "\\:~\0") === false               // no Windows tricks (C:, \, 8.3 short names)
    && !preg_match('/\.php$/i', $uri)
    && is_file($publicPath.$uri);

if ($isStatic) {
    return false;
}

$formattedDateTime = date('D M j H:i:s Y');
$requestMethod = $_SERVER['REQUEST_METHOD'];
$remoteAddress = $_SERVER['REMOTE_ADDR'].':'.$_SERVER['REMOTE_PORT'];

file_put_contents('php://stdout', "[$formattedDateTime] $remoteAddress [$requestMethod] URI: $uri\n");

// The built-in server reports the requested file as the script when it exists,
// which would make Laravel treat it as the base URL; route via index.php.
$_SERVER['SCRIPT_FILENAME'] = $publicPath.DIRECTORY_SEPARATOR.'index.php';
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';

require_once $publicPath.'/index.php';
