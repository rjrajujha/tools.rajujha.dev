<?php
declare(strict_types=1);

if (defined('APP_BOOTSTRAPPED')) {
    return;
}

define('APP_BOOTSTRAPPED', true);
define('APP_ROOT', __DIR__);

/**
 * Default debug flag. Override with APP_DEBUG=1 in the server environment.
 * Keep this false in production.
 */
define('APP_DEBUG', false);

@ini_set('expose_php', '0');
@ini_set('display_errors', '0');
if (!headers_sent()) {
    header_remove('X-Powered-By');
}

/** Maximum accepted API input size for str/key/ua payloads. */
define('APP_MAX_INPUT_BYTES', 65536);

/** bcrypt algorithm floor. Policy default/max come from config.json. */
define('APP_BCRYPT_COST_MIN', 4);
define('APP_BCRYPT_COST_ABS_MAX', 31);

/**
 * PBKDF2 floor for versioned payloads, and a config-file sanity ceiling.
 * Policy default/max iteration counts come from config.json.
 */
define('APP_PBKDF2_ITER_MIN', 100000);
define('APP_PBKDF2_ITER_ABS_MAX', 600000);

/** Historical binary encryption payload (pre-versioned JSON). */
define('APP_LEGACY_PBKDF2_ITERATIONS', 120000);

define('APP_ENC_VERSION_V1', 1);
define('APP_ENC_VERSION', 2);
define('APP_ENC_SALT_BYTES', 16);
define('APP_ENC_IV_BYTES', 12);
define('APP_ENC_TAG_BYTES', 16);
/** Compact opaque encoding magic: ASCII "TJ" + versioned binary fields. */
define('APP_ENC_COMPACT_MAGIC', 'TJ');
define('APP_ENC_COMPACT_HEADER_BYTES', 51);

/** Absolute safety bounds for rate_limit config. Policy defaults come from config.json. */
define('APP_RATE_LIMIT_REQUESTS_MIN', 1);
define('APP_RATE_LIMIT_REQUESTS_MAX', 120);
define('APP_RATE_LIMIT_WINDOW_MIN', 10);
define('APP_RATE_LIMIT_WINDOW_MAX', 3600);
define('APP_RATE_LIMIT_DIR_MAX_FILES', 8000);

function app_debug(): bool
{
    $env = getenv('APP_DEBUG');
    if ($env === false || $env === '') {
        return APP_DEBUG;
    }

    return in_array(strtolower((string) $env), ['1', 'true', 'yes', 'on'], true);
}

function is_api_request(): bool
{
    $path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';

    return $path === '/api.php'
        || $path === '/health'
        || str_starts_with($path, '/api/');
}

function app_config_defaults(): array
{
    return [
        'author' => 'Raju Jha',
        'version' => '1.1.0',
        'security' => [
            'bcrypt_cost' => 12,
            'max_bcrypt_cost' => 14,
            'encryption_iterations' => 310000,
            'max_encryption_iterations' => 310000,
        ],
        'rate_limit' => [
            'enabled' => true,
            'requests' => 20,
            'window_seconds' => 60,
        ],
        'client_ip' => [
            'trust_cloudflare' => false,
        ],
    ];
}

function app_config_invalid(): never
{
    render_error_page(500, 'Server error', 'Application configuration is invalid.');
}

function app_positive_int(mixed $value): ?int
{
    if (is_int($value) && $value > 0) {
        return $value;
    }

    if (is_string($value) && ctype_digit($value) && $value !== '0') {
        return (int) $value;
    }

    return null;
}

function app_strict_bool(mixed $value): ?bool
{
    return is_bool($value) ? $value : null;
}

function app_normalize_config(array $decoded, array $defaults): ?array
{
    $author = $decoded['author'] ?? $defaults['author'];
    $version = $decoded['version'] ?? $defaults['version'];
    if (!is_string($author) || $author === '' || !is_string($version) || $version === '') {
        return null;
    }

    $securityIn = is_array($decoded['security'] ?? null) ? $decoded['security'] : [];
    $securityDefaults = $defaults['security'];

    $bcryptCost = app_positive_int($securityIn['bcrypt_cost'] ?? $securityDefaults['bcrypt_cost']);
    $maxBcryptCost = app_positive_int($securityIn['max_bcrypt_cost'] ?? $securityDefaults['max_bcrypt_cost']);
    $encIter = app_positive_int($securityIn['encryption_iterations'] ?? $securityDefaults['encryption_iterations']);
    $maxEncIter = app_positive_int($securityIn['max_encryption_iterations'] ?? $securityDefaults['max_encryption_iterations']);

    if (
        $bcryptCost === null
        || $maxBcryptCost === null
        || $encIter === null
        || $maxEncIter === null
        || $bcryptCost < APP_BCRYPT_COST_MIN
        || $maxBcryptCost < APP_BCRYPT_COST_MIN
        || $bcryptCost > $maxBcryptCost
        || $maxBcryptCost > APP_BCRYPT_COST_ABS_MAX
        || $encIter < APP_PBKDF2_ITER_MIN
        || $maxEncIter < APP_PBKDF2_ITER_MIN
        || $encIter > $maxEncIter
        || $maxEncIter > APP_PBKDF2_ITER_ABS_MAX
    ) {
        return null;
    }

    $rateDefaults = $defaults['rate_limit'];
    if (array_key_exists('rate_limit', $decoded) && !is_array($decoded['rate_limit'])) {
        return null;
    }
    $rateIn = is_array($decoded['rate_limit'] ?? null) ? $decoded['rate_limit'] : [];
    $rateEnabled = array_key_exists('enabled', $rateIn)
        ? app_strict_bool($rateIn['enabled'])
        : $rateDefaults['enabled'];
    $rateRequests = array_key_exists('requests', $rateIn)
        ? app_positive_int($rateIn['requests'])
        : $rateDefaults['requests'];
    $rateWindow = array_key_exists('window_seconds', $rateIn)
        ? app_positive_int($rateIn['window_seconds'])
        : $rateDefaults['window_seconds'];

    if (
        $rateEnabled === null
        || $rateRequests === null
        || $rateWindow === null
        || $rateRequests < APP_RATE_LIMIT_REQUESTS_MIN
        || $rateRequests > APP_RATE_LIMIT_REQUESTS_MAX
        || $rateWindow < APP_RATE_LIMIT_WINDOW_MIN
        || $rateWindow > APP_RATE_LIMIT_WINDOW_MAX
    ) {
        return null;
    }

    $ipDefaults = $defaults['client_ip'];
    if (array_key_exists('client_ip', $decoded) && !is_array($decoded['client_ip'])) {
        return null;
    }
    $ipIn = is_array($decoded['client_ip'] ?? null) ? $decoded['client_ip'] : [];
    $trustCloudflare = array_key_exists('trust_cloudflare', $ipIn)
        ? app_strict_bool($ipIn['trust_cloudflare'])
        : $ipDefaults['trust_cloudflare'];

    if ($trustCloudflare === null) {
        return null;
    }

    return [
        'author' => $author,
        'version' => $version,
        'security' => [
            'bcrypt_cost' => $bcryptCost,
            'max_bcrypt_cost' => $maxBcryptCost,
            'encryption_iterations' => $encIter,
            'max_encryption_iterations' => $maxEncIter,
        ],
        'rate_limit' => [
            'enabled' => $rateEnabled,
            'requests' => $rateRequests,
            'window_seconds' => $rateWindow,
        ],
        'client_ip' => [
            'trust_cloudflare' => $trustCloudflare,
        ],
    ];
}

function app_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $defaults = app_config_defaults();
    $path = APP_ROOT . DIRECTORY_SEPARATOR . 'config.json';

    if (!is_file($path)) {
        $config = $defaults;
        return $config;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        app_config_invalid();
    }

    $normalized = app_normalize_config($decoded, $defaults);
    if ($normalized === null) {
        app_config_invalid();
    }

    $config = $normalized;
    return $config;
}

function app_security(): array
{
    return app_config()['security'];
}

function public_client_config(): array
{
    $security = app_security();
    $workerPath = APP_ROOT . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'regex-worker.js';

    return [
        'bcryptCost' => $security['bcrypt_cost'],
        'maxBcryptCost' => $security['max_bcrypt_cost'],
        'encryptionIterations' => $security['encryption_iterations'],
        'maxEncryptionIterations' => $security['max_encryption_iterations'],
        'regexWorker' => '/assets/regex-worker.js?v=' . (string) (@filemtime($workerPath) ?: '1'),
    ];
}

function app_send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header(
        "Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; "
        . "script-src 'self'; worker-src 'self'; connect-src 'self'; object-src 'none'; "
        . "base-uri 'self'; frame-ancestors 'self'; form-action 'self'"
    );
    header_remove('X-Powered-By');

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
    if ($https) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function app_no_store_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
    header('CDN-Cache-Control: no-store');
    header('Surrogate-Control: no-store');
}

function app_client_ip(): string
{
    $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $trustCloudflare = app_config()['client_ip']['trust_cloudflare'] === true;
    if ($trustCloudflare) {
        $cf = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
        if (filter_var($cf, FILTER_VALIDATE_IP)) {
            return $cf;
        }
    }

    if ($remote !== '' && filter_var($remote, FILTER_VALIDATE_IP)) {
        return $remote;
    }

    return '0.0.0.0';
}

function app_rate_limit_dir(): string
{
    $env = getenv('APP_RATE_LIMIT_DIR');
    if (!is_string($env) || $env === '') {
        $env = (string) ($_SERVER['APP_RATE_LIMIT_DIR'] ?? $_ENV['APP_RATE_LIMIT_DIR'] ?? '');
    }
    if ($env !== '' && !str_contains($env, "\0") && !str_contains($env, '..')) {
        return $env;
    }

    return APP_ROOT . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'rate-limit';
}

function app_rate_limit_maybe_cleanup(string $dir, int $window): void
{
    if (random_int(1, 40) !== 1) {
        return;
    }

    $files = glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [];
    $now = time();
    $removed = 0;

    foreach ($files as $file) {
        if ($removed >= 200) {
            break;
        }
        if (!preg_match('/[a-f0-9]{64}\\.json$/', $file)) {
            continue;
        }
        $mtime = @filemtime($file);
        if ($mtime !== false && ($now - $mtime) > ($window * 2)) {
            @unlink($file);
            $removed++;
        }
    }
}

/**
 * @return array{allowed: bool, retry_after: int, remaining: int}
 */
function app_rate_limit_hit(): array
{
    $cfg = app_config()['rate_limit'];
    $limit = $cfg['requests'];
    $window = $cfg['window_seconds'];
    $dir = app_rate_limit_dir();

    $open = [
        'allowed' => true,
        'retry_after' => $window,
        'remaining' => $limit,
    ];

    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        return $open;
    }

    $existing = glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [];
    if (count($existing) > APP_RATE_LIMIT_DIR_MAX_FILES) {
        app_rate_limit_maybe_cleanup($dir, $window);
        return $open;
    }

    app_rate_limit_maybe_cleanup($dir, $window);

    $id = hash('sha256', 'rl|' . app_client_ip());
    $path = $dir . DIRECTORY_SEPARATOR . $id . '.json';
    $handle = @fopen($path, 'c+');
    if ($handle === false) {
        return $open;
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return $open;
    }

    $raw = stream_get_contents($handle);
    $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    $now = time();
    $start = is_array($data) ? (int) ($data['w'] ?? 0) : 0;
    $count = is_array($data) ? (int) ($data['c'] ?? 0) : 0;

    if ($start <= 0 || ($now - $start) >= $window) {
        $start = $now;
        $count = 0;
    }

    $count++;
    $allowed = $count <= $limit;
    $remaining = max(0, $limit - $count);
    $retryAfter = max(1, $window - ($now - $start));

    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode(['w' => $start, 'c' => $count], JSON_UNESCAPED_SLASHES));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return [
        'allowed' => $allowed,
        'retry_after' => $retryAfter,
        'remaining' => $remaining,
    ];
}

function app_rate_limit_enforce(string $tool): void
{
    $cfg = app_config()['rate_limit'];
    if ($cfg['enabled'] !== true) {
        return;
    }

    $result = app_rate_limit_hit();
    if ($result['allowed']) {
        return;
    }

    if (!headers_sent()) {
        header('Retry-After: ' . (string) $result['retry_after']);
        header('X-RateLimit-Limit: ' . (string) $cfg['requests']);
        header('X-RateLimit-Remaining: 0');
    }

    json_error_response('Too many requests. Try again shortly.', 429, ['tool' => $tool]);
}

function health_response(): never
{
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        app_no_store_headers();
        header('X-Content-Type-Options: nosniff');
    }

    $config = app_config();

    echo app_json_encode([
        'status' => 'ok',
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'author' => $config['author'],
        'version' => $config['version'],
    ]);
    exit;
}

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function app_json_encode(array $payload): string
{
    $json = json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if (!is_string($json) || $json === '') {
        return '{"ok":false,"tool":null,"data":null,"error":{"code":"INTERNAL_ERROR","message":"Unable to encode response"}}';
    }

    return $json;
}

function app_error_code_for_status(int $status): string
{
    return match ($status) {
        404 => 'NOT_FOUND',
        405 => 'METHOD_NOT_ALLOWED',
        413 => 'PAYLOAD_TOO_LARGE',
        415 => 'UNSUPPORTED_MEDIA_TYPE',
        429 => 'RATE_LIMITED',
        500, 502, 503 => 'INTERNAL_ERROR',
        default => 'INVALID_PARAMETER',
    };
}

/**
 * @return array{code: string, message: string}
 */
function app_api_error(string $code, string $message): array
{
    return [
        'code' => $code,
        'message' => $message,
    ];
}

function json_error_response(string $message, int $status = 500, array $extra = []): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        app_no_store_headers();
        header('X-Content-Type-Options: nosniff');
    }

    $data = null;
    if (isset($extra['detail']) && is_string($extra['detail']) && $extra['detail'] !== '') {
        $data = ['detail' => $extra['detail']];
    }

    $code = isset($extra['code']) && is_string($extra['code']) && $extra['code'] !== ''
        ? $extra['code']
        : app_error_code_for_status($status);

    echo app_json_encode([
        'ok' => false,
        'tool' => $extra['tool'] ?? null,
        'data' => $data,
        'error' => app_api_error($code, $message),
    ]);
    exit;
}

function render_error_page(int $status, string $title, string $message, ?Throwable $throwable = null): never
{
    if (is_api_request()) {
        $extra = [];
        if (app_debug() && $throwable instanceof Throwable) {
            $extra['detail'] = $throwable->getMessage();
        }
        json_error_response($message, $status, $extra);
    }

    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
    }

    $codeLabel = (string) $status;
    $safeTitle = esc($title);
    $safeMessage = esc($message);
    $cssVersion = (string) @filemtime(APP_ROOT . '/assets/app.css');
    $detail = '';

    if (app_debug() && $throwable instanceof Throwable) {
        $detail = '<pre class="mt-8 overflow-x-auto rounded-2xl border border-line bg-white p-4 text-left text-xs leading-relaxed text-ink">'
            . esc($throwable->getMessage() . "\n\n" . $throwable->getFile() . ':' . $throwable->getLine() . "\n\n" . $throwable->getTraceAsString())
            . '</pre>';
    }

    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="robots" content="noindex">
  <meta name="theme-color" content="#f3f6f0">
  <title>{$safeTitle} · tools.rajujha.dev</title>
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/app.css?v={$cssVersion}">
</head>
<body class="min-h-dvh">
  <div class="mx-auto flex min-h-dvh max-w-3xl flex-col px-4 py-8 sm:px-5 sm:py-10">
    <a href="/" class="text-base font-extrabold tracking-tight text-ink sm:text-lg">tools<span class="font-medium text-muted">.rajujha.dev</span></a>
    <main class="my-auto py-12 text-center sm:py-16 sm:text-left">
      <p class="text-[0.7rem] font-extrabold tracking-[0.18em] text-leaf sm:text-xs">{$codeLabel}</p>
      <h1 class="mt-4 text-3xl font-bold tracking-tight text-ink sm:text-5xl">{$safeTitle}</h1>
      <p class="mt-4 max-w-xl text-sm leading-relaxed text-muted sm:text-base">{$safeMessage}</p>
      <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-start">
        <a href="/" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-ink px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-ink/90">Back to tools</a>
        <a href="https://github.com/rjrajujha/tools.rajujha.dev/issues" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-line bg-white px-4 py-2.5 text-sm font-semibold text-ink transition hover:border-leaf/40 hover:bg-soft" target="_blank" rel="noopener noreferrer">Report an issue</a>
      </div>
      {$detail}
    </main>
    <footer class="border-t border-line pt-5 text-xs text-muted">tools.rajujha.dev · Private developer utilities</footer>
  </div>
</body>
</html>
HTML;
    exit;
}

function register_error_handlers(): void
{
    error_reporting(E_ALL);
    ini_set('display_errors', app_debug() ? '1' : '0');
    ini_set('log_errors', '1');
    ini_set('expose_php', '0');
    if (!headers_sent()) {
        header_remove('X-Powered-By');
    }

    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    set_exception_handler(static function (Throwable $throwable): void {
        $message = app_debug()
            ? $throwable->getMessage()
            : 'Something went wrong while processing this request.';

        render_error_page(500, 'Server error', $message, $throwable);
    });

    register_shutdown_function(static function (): void {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($error['type'], $fatalTypes, true)) {
            return;
        }

        $throwable = new ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line']
        );

        $message = app_debug()
            ? $error['message']
            : 'Something went wrong while processing this request.';

        render_error_page(500, 'Server error', $message, $throwable);
    });
}

register_error_handlers();
app_send_security_headers();
