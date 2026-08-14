<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$routes = [
    '/' => 'home',
    '/password' => 'password',
    '/hash' => 'hash',
    '/timestamp' => 'timestamp',
    '/json' => 'json',
    '/uuid' => 'uuid',
    '/qr' => 'qr',
    '/regex' => 'regex',
    '/base64' => 'base64',
    '/jwt' => 'jwt',
    '/user-agent' => 'user-agent',
    '/markdown' => 'markdown',
    '/ip' => 'ip',
    '/secret' => 'secret',
    '/encryption' => 'encryption',
];

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$normalizedPath = rtrim($path, '/') ?: '/';

if (
    $normalizedPath === '/health'
    || (
        ($_GET['__route'] ?? '') === 'health'
        && ($normalizedPath === '/' || $normalizedPath === '/index.php')
    )
) {
    health_response();
}

if ($path === '/api.php') {
    require __DIR__ . '/api.php';
    exit;
}

$page = $routes[$normalizedPath] ?? null;

if ($page === null) {
    render_error_page(
        404,
        'Page not found',
        'The tool or page you requested does not exist. Check the URL or return to the tools index.'
    );
}

$tools = [
    ['password', 'Password Generator', 'Create strong random passwords'],
    ['hash', 'Hash', 'Hash text with SHA, MD5 or bcrypt'],
    ['timestamp', 'Timestamp', 'Convert Unix time and see current UTC'],
    ['json', 'JSON Decoder', 'Format, validate and inspect JSON'],
    ['uuid', 'UUID Generator', 'Generate UUID v4 identifiers locally'],
    ['qr', 'QR Code Generator', 'Create QR codes in your browser'],
    ['regex', 'Regex Tester', 'Test regular expressions safely'],
    ['base64', 'Base64', 'Encode and decode Base64 text'],
    ['jwt', 'JWT Decoder', 'Decode JWT header and payload locally'],
    ['user-agent', 'User-Agent Parser', 'Inspect browser and device information'],
    ['markdown', 'Markdown Preview', 'Preview Markdown instantly in your browser'],
    ['ip', 'IP Checker', 'See IPv4 and IPv6 observed by this server'],
    ['secret', 'Secret Generator', 'Generate cryptographic random secrets'],
    ['encryption', 'Encrypt-Decrypt', 'Encrypt and decrypt text with a secret key'],
];

$security = app_security();
$bcryptCost = $security['bcrypt_cost'];
$maxBcryptCost = $security['max_bcrypt_cost'];
$encIter = $security['encryption_iterations'];
$maxEncIter = $security['max_encryption_iterations'];

$meta = [
    'password' => [
        '/api/password',
        'GET',
        'length, upper, lower, numbers, symbols',
        'Optional API for scripts. UI stays in the browser.',
    ],
    'hash' => [
        '/api/hash',
        'GET, POST',
        'str, algorithm, cost?',
        'GET and POST. UI handles SHA-256/384/512 locally; API covers MD5, SHA-1, bcrypt, and all. Default bcrypt cost '
            . $bcryptCost . ' (max ' . $maxBcryptCost . '). GET query strings can be logged or cached — prefer POST for secrets.',
    ],
    'timestamp' => [
        '/api/timestamp',
        'GET',
        'timestamp?, unit?',
        'Omit params for current UTC, or pass a Unix timestamp to convert.',
    ],
    'json' => [null, null, null, 'Browser-only. No server API.'],
    'uuid' => [
        '/api/uuid',
        'GET',
        'count?',
        'Optional API for scripts. UI stays in the browser.',
    ],
    'qr' => [null, null, null, 'Browser-only. No server API.'],
    'regex' => [null, null, null, 'Browser-only. No server API.'],
    'base64' => [
        '/api/base64',
        'GET, POST',
        'str, mode',
        'GET and POST. mode is encode or decode. UI can also run locally. GET query strings can be logged or cached — prefer POST for secrets.',
    ],
    'jwt' => [null, null, null, 'Browser-only. Decoding does not verify signatures.'],
    'user-agent' => [
        '/api/user-agent',
        'GET',
        'ua?',
        'Uses the request User-Agent, or pass ua=… explicitly.',
    ],
    'markdown' => [null, null, null, 'Browser-only. No server API.'],
    'ip' => [
        '/api/ip',
        'GET',
        '—',
        'Returns REMOTE_ADDR for this connection. Proxy headers are listed and not trusted.',
    ],
    'secret' => [
        '/api/secret',
        'GET',
        'length, format, count?',
        'Optional API for scripts. UI stays in the browser.',
    ],
    'encryption' => [
        '/api/encryption',
        'GET, POST',
        'str, key, mode, v?, iter?',
        'GET and POST. UI stays in the browser. Default v=2; v=1 is legacy. Returns data.compact and data.json. '
            . 'PBKDF2 iterations default ' . number_format($encIter) . ' (max ' . number_format($maxEncIter) . '). '
            . 'GET query strings can be logged or cached — prefer POST for secrets.',
    ],
];

$examples = [
    'password' => 'GET /api/password?length=24&upper=1&lower=1&numbers=1&symbols=1',
    'hash' => "GET /api/hash?str=admin123&algorithm=sha256\n\nPOST /api/hash\nContent-Type: application/json\n\n{\"str\":\"admin123\",\"algorithm\":\"sha256\"}",
    'timestamp' => 'GET /api/timestamp?timestamp=1755000000&unit=s',
    'uuid' => 'GET /api/uuid?count=1',
    'base64' => "GET /api/base64?str=hello&mode=encode\n\nPOST /api/base64\nContent-Type: application/json\n\n{\"str\":\"hello\",\"mode\":\"encode\"}",
    'user-agent' => 'GET /api/user-agent',
    'ip' => 'GET /api/ip',
    'secret' => 'GET /api/secret?length=48&format=hex',
    'encryption' => "GET /api/encryption?str=hello&key=your-secret&mode=encrypt\n\nPOST /api/encryption\nContent-Type: application/json\n\n{\"str\":\"hello\",\"key\":\"your-secret\",\"mode\":\"encrypt\"}",
];

$cssVersion = (string) @filemtime(__DIR__ . '/assets/app.css');
$jsVersion = (string) @filemtime(__DIR__ . '/assets/app.js');

$currentTool = null;
foreach ($tools as $tool) {
    if ($tool[0] === $page) {
        $currentTool = $tool;
        break;
    }
}

$toolName = $currentTool[1] ?? ucwords(str_replace('-', ' ', (string) $page));
$toolDesc = $currentTool[2] ?? 'Fast, private developer utilities';
$siteUrl = 'https://tools.rajujha.dev';
$canonical = $page === 'home' ? $siteUrl . '/' : $siteUrl . '/' . $page;
$title = $page === 'home' ? 'Developer Tools' : $toolName . ' · Developer Tools';
$description = $page === 'home'
    ? 'Fast, private developer utilities. Passwords, hashes, JSON, JWT, Markdown, encryption and more — mostly in your browser, with optional JSON APIs.'
    : $toolDesc . ' Runs locally in your browser unless you explicitly use the optional API.';

$field = 'w-full rounded-xl border border-line bg-panel px-3.5 py-3 text-base text-ink outline-none transition placeholder:text-muted/70 focus:border-leaf/50 focus:ring-4 focus:ring-accent/25 sm:text-sm';
$controlSelect = 'h-11 w-full shrink-0 touch-manipulation rounded-xl border border-line bg-panel px-3 text-base text-ink outline-none transition focus:border-leaf/50 focus:ring-4 focus:ring-accent/25 sm:text-sm';
$btn = 'inline-flex h-11 min-h-11 w-full shrink-0 touch-manipulation cursor-pointer items-center justify-center rounded-xl border border-line bg-white px-4 text-sm font-semibold text-ink transition hover:border-leaf/40 hover:bg-soft active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60 motion-reduce:active:scale-100 sm:w-auto';
$btnPrimary = 'inline-flex h-11 min-h-11 w-full shrink-0 touch-manipulation cursor-pointer items-center justify-center rounded-xl bg-ink px-4 text-sm font-semibold text-white transition hover:bg-ink/90 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60 motion-reduce:active:scale-100 sm:w-auto';
$label = 'mb-2 block text-sm font-semibold text-ink';
$hint = 'mt-3 text-xs leading-relaxed text-muted';
$result = 'mt-4 flex flex-col items-stretch gap-3 rounded-2xl border border-line bg-soft p-4 sm:flex-row sm:items-start';
$resultBody = 'min-h-12 min-w-0 flex-1 overflow-x-auto whitespace-pre-wrap break-words font-mono text-sm text-ink';
$iconBtn = 'inline-flex size-10 touch-manipulation items-center justify-center rounded-xl text-muted transition hover:bg-white/80 hover:text-ink';
$iconSvg = 'size-5 fill-none stroke-current [stroke-linecap:round] [stroke-linejoin:round] [stroke-width:1.8]';
$stat = 'rounded-2xl border border-leaf/25 bg-moss px-4 py-4 text-left sm:px-5';
$check = 'inline-flex min-h-11 items-center gap-2 px-1 text-sm text-ink';
$controlRow = 'mt-3 flex w-full flex-col gap-2 sm:w-fit sm:flex-row sm:items-center sm:gap-2';

function tool_icon(string $slug, string $sizeClass = 'size-5'): string
{
    $inner = [
        'password' => '<circle cx="8" cy="11" r="3.5"/><path d="M11.5 11H20v2.5M16 11v2.5M19 11v4"/>',
        'hash' => '<path d="M9 4 7 20M17 4l-2 16M4 9h16M3 15h16"/>',
        'timestamp' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4.5l3 1.5"/>',
        'json' => '<path d="M8 5c-2.2 0-3 1.6-3 3.2v1.6c0 1.1-.8 1.7-2 1.7 1.2 0 2 .6 2 1.7v1.6c0 1.6.8 3.2 3 3.2"/><path d="M16 5c2.2 0 3 1.6 3 3.2v1.6c0 1.1.8 1.7 2 1.7-1.2 0-2 .6-2 1.7v1.6c0 1.6-.8 3.2-3 3.2"/>',
        'uuid' => '<rect x="4.5" y="4.5" width="15" height="15" rx="3"/><path d="M8 9h8M8 12h5M8 15h6"/>',
        'qr' => '<rect x="4" y="4" width="6.5" height="6.5" rx="1"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1"/><path d="M14 14h3v3h-3zM18.5 14H20v6h-6v-1.5h4.5z"/>',
        'regex' => '<path d="M7 18 15 6"/><path d="M16 14.5h4M18 12.5v4"/><circle cx="18" cy="16.5" r="0.4" fill="currentColor" stroke="none"/>',
        'base64' => '<rect x="4" y="5" width="16" height="14" rx="2"/><path d="M8 9h8M8 12h5M8 15h7"/>',
        'jwt' => '<rect x="6" y="4.5" width="12" height="4" rx="1"/><rect x="5" y="10" width="14" height="4" rx="1"/><rect x="6" y="15.5" width="12" height="4" rx="1"/>',
        'user-agent' => '<rect x="3.5" y="5" width="17" height="11" rx="2"/><path d="M8 20h8M12 16v4"/>',
        'markdown' => '<rect x="5" y="3.5" width="14" height="17" rx="2"/><path d="M8 15.5v-7l2.2 3.2L12.4 8.5v7M15 8.5v7l2.2-3"/>',
        'ip' => '<circle cx="12" cy="12" r="8"/><path d="M4 12h16M12 4c2.8 2.8 2.8 13.2 0 16M12 4c-2.8 2.8-2.8 13.2 0 16"/>',
        'secret' => '<path d="M12 3.5 19 7v4.5c0 4.4-3.1 7.3-7 8.2-3.9-.9-7-3.8-7-8.2V7l7-3.5z"/><circle cx="12" cy="11" r="1.6"/><path d="M12 12.6V15"/>',
        'encryption' => '<rect x="6" y="11" width="12" height="9" rx="2"/><path d="M8.5 11V8.2a3.5 3.5 0 0 1 7 0V11"/><path d="M12 14.2v2.2"/>',
    ];

    $markup = $inner[$slug] ?? '<circle cx="12" cy="12" r="7"/>';

    return '<svg class="' . $sizeClass . ' fill-none stroke-current [stroke-linecap:round] [stroke-linejoin:round] [stroke-width:1.8]" viewBox="0 0 24 24" aria-hidden="true">'
        . $markup
        . '</svg>';
}

function apiDocs(string $page, array $meta, array $examples, string $iconBtnClass, string $iconSvgClass): void
{
    [$endpoint, $method, $params, $note] = $meta;
    $wide = $page === 'markdown' ? 'max-w-6xl' : 'max-w-3xl';
    $hasApi = is_string($endpoint) && $endpoint !== '';
    $example = $hasApi ? (string) ($examples[$page] ?? '') : '';
    $exampleId = 'apiExample-' . preg_replace('/[^a-z0-9-]+/', '', $page);

    echo '<details class="group mx-auto mt-4 ' . $wide . ' overflow-hidden rounded-2xl border border-line bg-white/80 open:shadow-sm">';
    echo '<summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-4 text-sm font-extrabold text-ink marker:content-none sm:px-5 [&::-webkit-details-marker]:hidden">';
    echo '<span>How to use this tool as an API</span>';
    echo '<span class="text-muted transition group-open:hidden" aria-hidden="true">+</span>';
    echo '<span class="hidden text-muted group-open:inline" aria-hidden="true">−</span>';
    echo '</summary>';
    echo '<div class="space-y-3 border-t border-line px-4 py-4 sm:px-5">';
    echo '<p class="text-xs leading-relaxed text-muted">' . esc($note) . '</p>';

    if ($hasApi) {
        echo '<code class="block overflow-x-auto rounded-xl border border-line bg-soft px-3 py-2.5 font-mono text-xs text-ink">'
            . esc(trim((string) $method . ' ' . $endpoint))
            . '</code>';

        if (is_string($params) && $params !== '' && $params !== '—') {
            echo '<p class="text-xs text-muted">Params: <code class="font-mono text-ink">' . esc($params) . '</code></p>';
        }

        if ($example !== '') {
            echo '<div>';
            echo '<p class="mb-1 text-xs font-bold uppercase tracking-wide text-muted">Example</p>';
            echo '<div class="relative rounded-xl border border-line bg-soft">';
            echo '<button type="button" class="absolute right-2 top-2 z-10 inline-flex size-8 shrink-0 touch-manipulation cursor-pointer items-center justify-center rounded-lg border border-line bg-white text-muted shadow-sm transition hover:border-leaf/40 hover:bg-soft hover:text-ink active:scale-[0.98] active:bg-moss motion-reduce:active:scale-100" data-copy-target="#' . esc($exampleId)
                . '" data-copy-mode="icon" aria-label="Copy example">';
            echo '<svg class="' . esc($iconSvgClass) . ' size-4" viewBox="0 0 24 24" aria-hidden="true">'
                . '<rect x="9" y="9" width="11" height="11" rx="2"/>'
                . '<path d="M5 15V5a2 2 0 0 1 2-2h10"/>'
                . '</svg>';
            echo '</button>';
            echo '<code id="' . esc($exampleId)
                . '" class="block overflow-x-auto whitespace-pre-wrap pb-3 pl-4 pr-12 pt-11 font-mono text-xs leading-relaxed text-ink">'
                . esc($example)
                . '</code>';
            echo '</div></div>';
        }
    }

    echo '</div></details>';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="description" content="<?= esc($description) ?>">
  <meta name="theme-color" content="#f3f6f0">
  <meta name="color-scheme" content="light">
  <meta name="referrer" content="strict-origin-when-cross-origin">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="tools.rajujha.dev">
  <meta property="og:title" content="<?= esc($title) ?>">
  <meta property="og:description" content="<?= esc($description) ?>">
  <meta property="og:url" content="<?= esc($canonical) ?>">
  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="<?= esc($title) ?>">
  <meta name="twitter:description" content="<?= esc($description) ?>">
  <link rel="canonical" href="<?= esc($canonical) ?>">
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="/favicon.svg">
  <link rel="manifest" href="/site.webmanifest">
  <title><?= esc($title) ?></title>
  <link rel="stylesheet" href="/assets/app.css?v=<?= esc($cssVersion) ?>">
</head>
<body class="flex min-h-dvh flex-col overflow-x-hidden">
  <a class="absolute left-4 top-0 z-50 -translate-y-full rounded-b-xl bg-ink px-4 py-2 text-sm font-semibold text-white focus:translate-y-0" href="#main">Skip to content</a>
  <header class="sticky top-0 z-20 border-b border-line/80 bg-soft/80 pt-[env(safe-area-inset-top)] backdrop-blur-md">
    <div class="mx-auto flex h-14 max-w-6xl items-center justify-between gap-3 px-4 sm:h-16 sm:px-5">
      <a class="min-w-0 truncate text-base font-extrabold tracking-tight text-ink sm:text-lg" href="/">tools<span class="font-medium text-muted">.rajujha.dev</span></a>
      <nav class="flex shrink-0 items-center gap-1 text-muted" aria-label="Primary">
        <button type="button" id="openSearch" class="<?= $iconBtn ?>" aria-label="Search tools" aria-haspopup="dialog" aria-controls="searchModal">
          <svg class="<?= $iconSvg ?>" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        </button>
        <a class="<?= $iconBtn ?>" href="https://github.com/rjrajujha/tools.rajujha.dev" target="_blank" rel="noopener noreferrer" aria-label="GitHub repository">
          <svg class="size-5 fill-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-3.16 19.49c.5.09.68-.22.68-.48v-1.7c-2.78.6-3.37-1.34-3.37-1.34-.46-1.16-1.11-1.47-1.11-1.47-.91-.62.07-.61.07-.61 1 .07 1.53 1.03 1.53 1.03.9 1.52 2.34 1.08 2.91.83.09-.65.35-1.08.63-1.33-2.22-.25-4.56-1.11-4.56-4.95 0-1.09.39-1.99 1.03-2.69-.1-.25-.45-1.27.1-2.64 0 0 .84-.27 2.75 1.02A9.56 9.56 0 0 1 12 6.84a9.6 9.6 0 0 1 2.5.34c1.91-1.29 2.75-1.02 2.75-1.02.55 1.37.2 2.39.1 2.64.64.7 1.03 1.6 1.03 2.69 0 3.85-2.34 4.7-4.57 4.95.36.31.68.92.68 1.85v2.74c0 .27.18.58.69.48A10 10 0 0 0 12 2Z"/></svg>
        </a>
      </nav>
    </div>
  </header>

  <dialog id="searchModal" class="m-0 max-h-none w-full max-w-none border-0 bg-transparent p-4 pt-[12vh] open:grid open:justify-items-center" aria-labelledby="searchTitle">
    <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-line bg-white shadow-xl shadow-ink/10">
      <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
        <strong id="searchTitle" class="text-sm font-bold text-ink">Search tools</strong>
        <button type="button" class="<?= $iconBtn ?>" id="closeSearch" aria-label="Close search">
          <svg class="<?= $iconSvg ?>" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </div>
      <div class="px-4 pt-3">
        <label class="sr-only" for="toolSearch">Search tools</label>
        <input id="toolSearch" type="search" class="<?= $field ?>" placeholder="Type a tool name" autocomplete="off" spellcheck="false">
      </div>
      <div class="max-h-[min(60vh,28rem)] overflow-y-auto p-2" id="searchResults">
        <?php foreach ($tools as [$slug, $name, $desc]): ?>
          <a class="flex items-start gap-3 rounded-xl px-3 py-2.5 text-left transition hover:bg-soft" data-search-item data-search="<?= esc($name . ' ' . $desc . ' ' . $slug) ?>" href="/<?= esc($slug) ?>">
            <span class="mt-0.5 grid size-9 shrink-0 place-items-center rounded-lg bg-moss text-ink"><?= tool_icon($slug, 'size-4') ?></span>
            <span class="min-w-0">
              <span class="block truncate text-sm font-bold text-ink"><?= esc($name) ?></span>
              <span class="mt-0.5 block truncate text-xs text-muted"><?= esc($desc) ?></span>
            </span>
          </a>
        <?php endforeach; ?>
        <p id="toolEmpty" class="px-3 py-6 text-center text-sm text-muted" hidden>No tools match that search.</p>
      </div>
    </div>
  </dialog>

  <main id="main" class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-5 sm:py-12 lg:py-14">
<?php if ($page === 'home'): ?>
    <section class="mx-auto max-w-3xl text-center sm:text-left">
      <p class="text-[0.7rem] font-extrabold tracking-[0.18em] text-leaf sm:text-xs">PRIVATE · FAST · OPEN SOURCE</p>
      <h1 class="mt-3 text-[2rem] font-bold tracking-tight text-ink sm:mt-4 sm:text-5xl sm:leading-[0.95] lg:text-6xl">
        Developer tools,<br><span class="text-leaf">without the clutter.</span>
      </h1>
    </section>

    <section class="mt-8 grid grid-cols-1 gap-3 sm:mt-10 sm:grid-cols-2 lg:grid-cols-3" aria-label="Available tools">
      <?php foreach ($tools as [$slug, $name, $desc]): ?>
        <a class="group flex h-full items-start gap-3 rounded-2xl border border-line bg-white/80 p-4 text-left transition hover:border-leaf/30 hover:shadow-lg hover:shadow-ink/5 motion-safe:hover:-translate-y-0.5 sm:gap-4 sm:p-5" href="/<?= esc($slug) ?>">
          <span class="mt-0.5 grid size-11 shrink-0 place-items-center rounded-xl bg-moss text-ink"><?= tool_icon($slug, 'size-5') ?></span>
          <span class="min-w-0 flex-1">
            <h2 class="text-base font-bold leading-snug text-ink group-hover:text-leaf"><?= esc($name) ?></h2>
            <p class="mt-1 line-clamp-2 min-h-10 text-sm leading-5 text-muted"><?= esc($desc) ?></p>
          </span>
        </a>
      <?php endforeach; ?>
    </section>

<?php else: ?>
    <section class="mb-5 sm:mb-6">
      <nav class="mb-3 flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="/" class="rounded-lg py-1 transition hover:text-ink">All tools</a>
        <span aria-hidden="true">/</span>
        <span class="text-ink" aria-current="page"><?= esc($toolName) ?></span>
      </nav>
      <h1 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-ink sm:text-3xl">
        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-moss text-ink"><?= tool_icon($page, 'size-5') ?></span>
        <span><?= esc($toolName) ?></span>
      </h1>
      <p class="mt-1 max-w-2xl text-sm leading-relaxed text-muted"><?= esc($toolDesc) ?></p>
    </section>

    <section class="<?= $page === 'markdown' ? 'max-w-6xl' : 'max-w-3xl' ?> mx-auto rounded-2xl border border-line bg-white p-4 shadow-xl shadow-ink/5 sm:rounded-3xl sm:p-7" data-tool="<?= esc($page) ?>">
<?php if ($page === 'password'): ?>
      <div class="flex items-center justify-between gap-3">
        <label class="<?= $label ?> mb-0" for="len">Length</label>
        <output id="lenOut" class="font-mono text-sm text-muted" for="len">24</output>
      </div>
      <input id="len" class="mt-1" type="range" min="8" max="128" value="24">
      <div class="mt-2 flex flex-wrap gap-1 sm:gap-2">
        <label class="<?= $check ?>"><input id="upper" type="checkbox" checked class="size-4 accent-ink"> Uppercase</label>
        <label class="<?= $check ?>"><input id="lower" type="checkbox" checked class="size-4 accent-ink"> Lowercase</label>
        <label class="<?= $check ?>"><input id="numbers" type="checkbox" checked class="size-4 accent-ink"> Numbers</label>
        <label class="<?= $check ?>"><input id="symbols" type="checkbox" checked class="size-4 accent-ink"> Symbols</label>
      </div>
      <div class="<?= $result ?>">
        <code id="passwordOut" class="<?= $resultBody ?>" aria-live="polite"></code>
        <button id="copy" type="button" class="<?= $btn ?>">Copy</button>
      </div>
      <button class="<?= $btnPrimary ?> mt-4" id="generate" type="button">Generate password</button>
      <p class="<?= $hint ?>">Choose options, then generate. Runs in your browser.</p>

<?php elseif ($page === 'hash'): ?>
      <label class="<?= $label ?>" for="input">Text</label>
      <textarea id="input" rows="6" class="<?= $field ?>" placeholder="admin123" spellcheck="false"></textarea>
      <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
        <label class="sr-only" for="algorithm">Hash algorithm</label>
        <select id="algorithm" class="<?= $controlSelect ?> sm:min-w-0 sm:flex-1">
          <option value="sha256">SHA-256</option>
          <option value="sha384">SHA-384</option>
          <option value="sha512">SHA-512</option>
          <option value="md5">MD5</option>
          <option value="sha1">SHA-1</option>
          <option value="bcrypt">bcrypt</option>
          <option value="all">All supported hashes</option>
        </select>
        <input id="cost" type="number" min="<?= (int) APP_BCRYPT_COST_MIN ?>" max="<?= (int) $maxBcryptCost ?>" value="<?= (int) $bcryptCost ?>" title="bcrypt cost" aria-label="bcrypt cost" class="<?= $controlSelect ?> hidden sm:w-28">
        <button class="<?= $btnPrimary ?>" id="run" type="button">Hash</button>
      </div>
      <p class="<?= $hint ?>">Enter text, pick an algorithm, then hash. SHA-256/384/512 stay local; MD5, SHA-1, bcrypt, and All use POST. Ctrl+Enter to run.</p>
      <div class="<?= $result ?>">
        <pre id="output" class="<?= $resultBody ?>" aria-live="polite"></pre>
        <button id="copy" type="button" class="<?= $btn ?>">Copy</button>
      </div>

<?php elseif ($page === 'timestamp'): ?>
      <div class="<?= $stat ?> mb-5">
        <span class="text-xs text-muted">Current UTC</span>
        <strong id="currentTimestamp" class="mt-1 block font-mono text-xl font-bold tracking-tight sm:text-2xl">Loading…</strong>
        <small id="currentUtc" class="mt-1 block text-xs text-muted"></small>
      </div>
      <label class="<?= $label ?>" for="input">Unix timestamp</label>
      <input id="input" inputmode="decimal" placeholder="1755000000" class="<?= $field ?>" autocomplete="off">
      <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
        <label class="sr-only" for="unit">Timestamp unit</label>
        <select id="unit" class="<?= $controlSelect ?> sm:min-w-0 sm:flex-1">
          <option value="s">Seconds</option>
          <option value="ms">Milliseconds</option>
        </select>
        <button class="<?= $btnPrimary ?>" id="run" type="button">Convert</button>
      </div>
      <div class="<?= $result ?>">
        <pre id="output" class="<?= $resultBody ?>" aria-live="polite"></pre>
        <button id="copy" type="button" class="<?= $btn ?>">Copy</button>
      </div>

<?php elseif ($page === 'json'): ?>
      <p class="<?= $hint ?> mt-0 mb-3">Paste JSON, then format or minify. Stays in your browser.</p>
      <label class="<?= $label ?>" for="input">JSON</label>
      <textarea id="input" rows="10" class="<?= $field ?>" placeholder='{"name":"John Doe","active":true}' spellcheck="false"></textarea>
      <div class="mt-3 flex flex-col gap-2 sm:flex-row">
        <button class="<?= $btnPrimary ?>" id="run" type="button">Format JSON</button>
        <button id="minify" type="button" class="<?= $btn ?>">Minify</button>
      </div>
      <div class="<?= $result ?>">
        <pre id="output" class="<?= $resultBody ?>" aria-live="polite"></pre>
        <button id="copy" type="button" class="<?= $btn ?>">Copy</button>
      </div>

<?php elseif ($page === 'uuid'): ?>
      <div class="<?= $result ?> mt-0">
        <code id="output" class="<?= $resultBody ?>" aria-live="polite"></code>
        <button id="copy" type="button" class="<?= $btn ?>">Copy</button>
      </div>
      <button class="<?= $btnPrimary ?> mt-4" id="run" type="button">Generate UUID</button>
      <p class="<?= $hint ?>">Generate a UUID v4 in your browser.</p>

<?php elseif ($page === 'qr'): ?>
      <label class="<?= $label ?>" for="input">Text or URL</label>
      <input id="input" placeholder="https://example.com" class="<?= $field ?>" autocomplete="off" spellcheck="false">
      <label class="<?= $label ?> mt-4" for="qrLevel">Error correction</label>
      <select id="qrLevel" class="<?= $field ?>">
        <option value="L">L ~7% recovery</option>
        <option value="M" selected>M ~15% recovery</option>
        <option value="Q">Q ~25% recovery</option>
        <option value="H">H ~30% recovery</option>
      </select>
      <div id="qr" class="mt-4 grid min-h-48 place-items-center rounded-2xl border border-dashed border-line bg-panel px-4 text-center text-sm text-muted sm:min-h-56">Enter text, then generate</div>
      <div class="mt-4 flex flex-col gap-2 sm:flex-row">
        <button class="<?= $btnPrimary ?>" id="run" type="button">Generate QR</button>
        <button id="qrDownloadPng" type="button" class="<?= $btn ?>" disabled>Download PNG</button>
        <button id="qrDownloadSvg" type="button" class="<?= $btn ?>" disabled>Download SVG</button>
      </div>
      <p class="<?= $hint ?>">Enter text or a URL, then generate. Stays in your browser.</p>

<?php elseif ($page === 'regex'): ?>
      <label class="<?= $label ?>" for="pattern">Regular expression</label>
      <input id="pattern" placeholder="^[A-Z][a-z]+$" class="<?= $field ?>" spellcheck="false" autocomplete="off">
      <label class="<?= $label ?> mt-4" for="input">Test string</label>
      <textarea id="input" rows="5" placeholder="John Doe" class="<?= $field ?>" spellcheck="false"></textarea>
      <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
        <input id="flags" value="g" placeholder="flags" class="<?= $field ?>" spellcheck="false" autocomplete="off" aria-label="Regex flags">
        <button class="<?= $btnPrimary ?>" id="run" type="button">Test</button>
      </div>
      <div class="<?= $result ?>">
        <pre id="output" class="<?= $resultBody ?>" aria-live="polite"></pre>
      </div>
      <p class="<?= $hint ?>">Enter a pattern and test string, then run. Executes in a Web Worker.</p>

<?php elseif ($page === 'base64'): ?>
      <label class="<?= $label ?>" for="input">Text</label>
      <textarea id="input" rows="7" placeholder="Hello world" class="<?= $field ?>" spellcheck="false"></textarea>
      <div class="mt-3 flex flex-col gap-2 sm:flex-row">
        <button class="<?= $btnPrimary ?>" id="encode" type="button">Encode</button>
        <button id="decode" type="button" class="<?= $btn ?>">Decode</button>
      </div>
      <div class="<?= $result ?>">
        <pre id="output" class="<?= $resultBody ?>" aria-live="polite"></pre>
        <button id="copy" type="button" class="<?= $btn ?>">Copy</button>
      </div>
      <p class="<?= $hint ?>">Enter text, then encode or decode. Runs in your browser.</p>

<?php elseif ($page === 'jwt'): ?>
      <label class="<?= $label ?>" for="input">JWT</label>
      <textarea id="input" rows="6" placeholder="Paste a JWT here" autocomplete="off" class="<?= $field ?>" spellcheck="false"></textarea>
      <button class="<?= $btnPrimary ?> mt-3" id="run" type="button">Decode JWT</button>
      <div class="mt-4 grid gap-3 md:grid-cols-2">
        <div class="rounded-2xl border border-line bg-soft p-4">
          <strong class="mb-2 block text-xs font-bold uppercase tracking-wide text-muted">Header</strong>
          <pre id="headerOut" class="overflow-x-auto whitespace-pre-wrap break-words font-mono text-sm"></pre>
        </div>
        <div class="rounded-2xl border border-line bg-soft p-4">
          <strong class="mb-2 block text-xs font-bold uppercase tracking-wide text-muted">Payload</strong>
          <pre id="payloadOut" class="overflow-x-auto whitespace-pre-wrap break-words font-mono text-sm"></pre>
        </div>
      </div>
      <p class="<?= $hint ?>">Paste a token, then decode. Signature is not verified.</p>

<?php elseif ($page === 'user-agent'): ?>
      <label class="<?= $label ?>" for="input">User-Agent string</label>
      <textarea id="input" rows="4" placeholder="Edit to re-parse instantly" class="<?= $field ?>" spellcheck="false"></textarea>
      <div class="mt-4 grid gap-3 sm:grid-cols-2" id="uaCards"></div>
      <div class="<?= $result ?>">
        <pre id="uaOutput" class="<?= $resultBody ?>" aria-live="polite"></pre>
        <button id="copy" type="button" class="<?= $btn ?>">Copy</button>
      </div>
      <p class="<?= $hint ?>">Edit the string to re-parse. Starts with this browser’s User-Agent.</p>

<?php elseif ($page === 'markdown'): ?>
      <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <strong class="block text-sm font-bold text-ink">Markdown Preview</strong>
          <span class="text-xs text-muted" id="mdStats">0 characters · processed locally</span>
        </div>
        <div class="grid grid-cols-3 gap-2 sm:flex sm:flex-wrap">
          <button id="mdSample" type="button" class="<?= $btn ?>">Sample</button>
          <button id="mdClear" type="button" class="<?= $btn ?>">Clear</button>
          <button id="copy" type="button" class="<?= $btn ?>">Copy</button>
        </div>
      </div>
      <div class="grid min-h-[min(70vh,720px)] gap-3 lg:h-[min(70vh,720px)] lg:grid-cols-2">
        <div class="flex min-h-64 flex-col overflow-hidden rounded-2xl border border-line bg-panel lg:min-h-0">
          <div class="border-b border-line bg-white/70 px-4 py-3">
            <label class="text-sm font-semibold text-ink" for="input">Editor</label>
          </div>
          <textarea id="input" class="min-h-0 w-full flex-1 resize-none border-0 bg-[#fcfdfb] px-4 py-4 font-mono text-base leading-relaxed text-ink outline-none focus:ring-0 sm:text-sm" placeholder="# Hello&#10;&#10;Write **Markdown** here.&#10;&#10;- Fast&#10;- Local&#10;- Private" spellcheck="false"></textarea>
        </div>
        <div class="flex min-h-64 flex-col overflow-hidden rounded-2xl border border-line bg-white lg:min-h-0">
          <div class="border-b border-line bg-white/70 px-4 py-3">
            <label class="text-sm font-semibold text-ink">Preview</label>
          </div>
          <article id="preview" class="markdown-preview min-h-0 flex-1 overflow-auto px-4 py-4 sm:px-5 sm:py-5"></article>
        </div>
      </div>

<?php elseif ($page === 'ip'): ?>
      <div class="grid gap-3 sm:grid-cols-2">
        <div class="ip-card <?= $stat ?>">
          <span class="text-xs text-muted">IPv4</span>
          <strong id="ipv4Output" class="mt-1 block break-all font-mono text-xl font-bold tracking-tight sm:text-2xl">Checking…</strong>
          <small id="ipv4Note" class="mt-1 block text-xs text-muted">Server-observed address</small>
        </div>
        <div class="ip-card <?= $stat ?>">
          <span class="text-xs text-muted">IPv6</span>
          <strong id="ipv6Output" class="mt-1 block break-all font-mono text-xl font-bold tracking-tight sm:text-2xl">Checking…</strong>
          <small id="ipv6Note" class="mt-1 block text-xs text-muted">Server-observed address</small>
        </div>
      </div>
      <div class="<?= $stat ?> mt-3">
        <span class="text-xs text-muted">Primary observed IP</span>
        <strong id="ipOutput" class="mt-1 block break-all font-mono text-xl font-bold tracking-tight sm:text-2xl">Checking…</strong>
        <small id="ipVersion" class="mt-1 block text-xs text-muted">Read from your request</small>
      </div>
      <div class="<?= $result ?>">
        <pre id="ipDetails" class="<?= $resultBody ?>" aria-live="polite"></pre>
        <button id="copy" type="button" class="<?= $btn ?>">Copy</button>
      </div>
      <button class="<?= $btnPrimary ?> mt-4" id="run" type="button">Refresh IP</button>
      <p class="<?= $hint ?>">Shows the server-observed address for this connection. Proxy headers are not trusted.</p>

<?php elseif ($page === 'secret'): ?>
      <div class="flex items-center justify-between gap-3">
        <label class="<?= $label ?> mb-0" for="secretLen">Length</label>
        <output id="secretLenOut" class="font-mono text-sm text-muted" for="secretLen">48</output>
      </div>
      <input id="secretLen" class="mt-1" type="range" min="16" max="256" value="48">
      <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
        <select id="secretFormat" class="<?= $controlSelect ?> sm:min-w-0 sm:flex-1" aria-label="Secret format">
          <option value="hex">Hex</option>
          <option value="base64url">Base64URL</option>
          <option value="base64">Base64</option>
        </select>
        <button class="<?= $btnPrimary ?>" id="run" type="button">Generate secret</button>
      </div>
      <div class="<?= $result ?>">
        <code id="secretOutput" class="<?= $resultBody ?>" aria-live="polite"></code>
        <button id="copy" type="button" class="<?= $btn ?>">Copy</button>
      </div>
      <p class="<?= $hint ?>">Choose length and format, then generate. Runs in your browser.</p>

<?php elseif ($page === 'encryption'): ?>
      <label class="<?= $label ?>" for="input" id="inputLabel">String / Input</label>
      <textarea id="input" rows="5" placeholder="Hello world" class="<?= $field ?>" spellcheck="false"></textarea>
      <label class="<?= $label ?> mt-4" for="key">Secret Key</label>
      <input id="key" value="" autocomplete="off" class="<?= $field ?>" spellcheck="false" placeholder="A long random secret">
      <p class="mt-2 text-sm leading-relaxed text-ink">Use <a class="font-semibold text-leaf underline underline-offset-2 hover:text-ink" href="/secret">/secret</a> for a strong key.</p>
      <div class="<?= $controlRow ?> mt-4" role="group" aria-label="Encrypt or decrypt action">
        <select
          id="mode"
          class="<?= $controlSelect ?> sm:w-36"
          autocomplete="off"
          aria-label="Encrypt or decrypt"
        >
          <option value="encrypt" selected>Encrypt</option>
          <option value="decrypt">Decrypt</option>
        </select>
        <button class="<?= $btnPrimary ?> sm:min-w-28 sm:px-5" id="run" type="button" data-label="Encrypt">Encrypt</button>
      </div>

      <div id="encryptOutputs" class="mt-4 space-y-3" hidden>
        <article class="rounded-2xl border border-line bg-soft p-4">
          <h2 class="text-sm font-extrabold text-ink">Compact Encrypted Text</h2>
          <p class="mt-1 text-xs leading-relaxed text-muted">Opaque Base64 representation of the same encryption result.</p>
          <pre id="compactOutput" class="mt-3 min-h-12 overflow-x-auto whitespace-pre-wrap break-words font-mono text-sm text-ink" aria-live="polite"></pre>
          <button id="copyCompact" type="button" class="<?= $btn ?> mt-3">Copy</button>
        </article>
        <article class="rounded-2xl border border-line bg-soft p-4">
          <h2 class="text-sm font-extrabold text-ink">Encrypted JSON / Object</h2>
          <p class="mt-1 text-xs leading-relaxed text-muted">Structured metadata and ciphertext from the same operation.</p>
          <pre id="jsonOutput" class="mt-3 min-h-12 overflow-x-auto whitespace-pre-wrap break-words font-mono text-sm text-ink" aria-live="polite"></pre>
          <button id="copyJson" type="button" class="<?= $btn ?> mt-3">Copy</button>
        </article>
        <p class="text-xs leading-relaxed text-muted">Both cards are one encryption. Base64 is encoding, not encryption. The secret is never included.</p>
      </div>

      <div id="decryptOutputs" class="<?= $result ?>" hidden>
        <div class="min-w-0 flex-1">
          <p class="mb-2 text-xs font-bold uppercase tracking-wide text-muted">Decrypted Text</p>
          <pre id="decryptOutput" class="<?= $resultBody ?> mt-0" aria-live="polite"></pre>
        </div>
        <button id="copyDecrypt" type="button" class="<?= $btn ?>">Copy</button>
      </div>

      <div id="encError" class="<?= $result ?>" hidden role="alert">
        <pre id="errorOutput" class="<?= $resultBody ?> text-ink" aria-live="polite"></pre>
      </div>

      <p class="<?= $hint ?>" id="encHint">Enter text and a secret, choose Encrypt or Decrypt, then run. Stays in your browser. Ctrl+Enter to run.</p>
<?php endif; ?>
    </section>
    <?php apiDocs($page, $meta[$page], $examples, $iconBtn, $iconSvg); ?>
<?php endif; ?>
  </main>

  <footer class="mx-auto w-full max-w-6xl border-t border-line px-4 py-5 text-xs text-muted sm:px-5">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <span>tools.rajujha.dev</span>
      <span>No analytics · No accounts · Client-first</span>
    </div>
  </footer>
  <div id="toast" class="pointer-events-none fixed bottom-[max(1.25rem,env(safe-area-inset-bottom))] left-1/2 z-40 -translate-x-1/2 rounded-full bg-ink px-4 py-2 text-sm font-semibold text-white opacity-0 shadow-lg transition aria-hidden:opacity-0" role="status" aria-live="polite" aria-hidden="true"></div>
  <script type="application/json" id="app-config"><?= json_encode(public_client_config(), JSON_UNESCAPED_SLASHES) ?></script>
  <?php if ($page === 'qr'): ?>
  <script src="/assets/vendor/qrcode-generator.js?v=<?= esc((string) @filemtime(__DIR__ . '/assets/vendor/qrcode-generator.js')) ?>" defer></script>
  <script src="/assets/vendor/qrcode-generator-utf8.js?v=<?= esc((string) @filemtime(__DIR__ . '/assets/vendor/qrcode-generator-utf8.js')) ?>" defer></script>
  <?php endif; ?>
  <script src="/assets/app.js?v=<?= esc($jsVersion) ?>" defer></script>
</body>
</html>
