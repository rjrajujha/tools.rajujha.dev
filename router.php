<?php
declare(strict_types=1);

/**
 * Local PHP built-in server front controller only.
 *
 *   php -S 127.0.0.1:8080 router.php
 *
 * Production Apache hosts use .htaccess instead. Direct web access to this
 * file is blocked there so the two routers never conflict.
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$normalized = strtolower(rtrim($uri, '/') ?: '/');

if (str_contains($uri, '..')) {
    require_once __DIR__ . '/bootstrap.php';
    render_error_page(400, 'Bad request', 'The requested path is not valid.');
}

$blockedExact = [
    '/config.json',
    '/bootstrap.php',
    '/router.php',
    '/.env',
    '/.gitignore',
    '/package.json',
    '/package-lock.json',
    '/composer.json',
    '/composer.lock',
    '/readme.md',
    '/license',
];

$blockedPrefix = ['/.git', '/src/', '/node_modules/', '/.env', '/var/', '/tests/', '/.github/'];

if (in_array($normalized, $blockedExact, true)) {
    require_once __DIR__ . '/bootstrap.php';
    render_error_page(404, 'Page not found', 'The tool or page you requested does not exist.');
}

foreach ($blockedPrefix as $prefix) {
    if ($normalized === rtrim($prefix, '/') || str_starts_with($normalized, $prefix)) {
        require_once __DIR__ . '/bootstrap.php';
        render_error_page(404, 'Page not found', 'The tool or page you requested does not exist.');
    }
}

$file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $uri);

if ($uri !== '/' && is_file($file)) {
    return false;
}

require_once __DIR__ . '/bootstrap.php';

if (preg_match('#^/health/?$#i', $uri)) {
    health_response();
}

if (preg_match('#^/api/(password|hash|timestamp|uuid|base64|user-agent|ip|secret|encryption)/?$#i', $uri, $match)) {
    $_GET['tool'] = strtolower($match[1]);
    require __DIR__ . '/api.php';
    return true;
}

require __DIR__ . '/index.php';
return true;
