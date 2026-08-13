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
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

    return $path === '/api.php' || str_starts_with($path, '/api/');
}

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function json_error_response(string $message, int $status = 500, array $extra = []): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
    }

    echo json_encode(
        array_merge(['ok' => false, 'error' => $message], $extra),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );
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
