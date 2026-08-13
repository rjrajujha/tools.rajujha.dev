<?php
declare(strict_types=1);

/**
 * Local PHP built-in server front controller only.
 *
 *   php -S 127.0.0.1:8080 router.php
 *
 * Apache / LiteSpeed must use .htaccess instead. Direct web access to this
 * file is blocked there so the two routers never conflict.
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $uri);

if ($uri !== '/' && is_file($file)) {
    return false;
}

require_once __DIR__ . '/bootstrap.php';

if (preg_match('#^/api/(password|hash|timestamp|uuid|base64|user-agent|ip|secret|encryption)/?$#i', $uri, $match)) {
    $_GET['tool'] = strtolower($match[1]);
    require __DIR__ . '/api.php';
    return true;
}

require __DIR__ . '/index.php';
return true;
