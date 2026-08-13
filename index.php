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

if ($path === '/api.php') {
    require __DIR__ . '/api.php';
    exit;
}

$page = $routes[rtrim($path, '/') ?: '/'] ?? null;

if ($page === null) {
    render_error_page(
        404,
        'Page not found',
        'The tool or page you requested does not exist. Check the URL or return to the tools index.'
    );
}

$tools = [
    ['password', 'Password Generator', 'Create strong random passwords', 'PW'],
    ['hash', 'Hash', 'Hash text with SHA, MD5 or bcrypt', '#'],
    ['timestamp', 'Timestamp', 'Convert Unix time and see current UTC', 'TS'],
    ['json', 'JSON Decoder', 'Format, validate and inspect JSON', '{}'],
    ['uuid', 'UUID Generator', 'Generate UUID v4 identifiers locally', 'ID'],
    ['qr', 'QR Code Generator', 'Create QR codes in your browser', 'QR'],
    ['regex', 'Regex Tester', 'Test regular expressions safely', '.*'],
    ['base64', 'Base64', 'Encode and decode Base64 text', '64'],
    ['jwt', 'JWT Decoder', 'Decode JWT header and payload locally', 'JWT'],
    ['user-agent', 'User-Agent Parser', 'Inspect browser and device information', 'UA'],
    ['markdown', 'Markdown Preview', 'Preview Markdown instantly in your browser', 'MD'],
    ['ip', 'IP Checker', 'See IPv4 and IPv6 observed by this server', 'IP'],
    ['secret', 'Secret Generator', 'Generate cryptographic random secrets', 'SX'],
    ['encryption', 'Encrypt / Decrypt', 'Encrypt and decrypt text with a secret key', 'AE'],
];

$meta = [
    'password' => ['/api/password', 'GET', 'length, count, upper, lower, numbers, symbols', 'UI uses browser crypto. Optional API for scripts; nothing is stored.'],
    'hash' => ['/api/hash', 'GET', 'str, algorithm, cost', 'UI uses Web Crypto for SHA-256/384/512. API covers MD5, SHA-1, bcrypt, and all.'],
    'timestamp' => ['/api/timestamp', 'GET', 'timestamp, unit', 'UI clock is local. API converts timestamps or returns current UTC.'],
    'json' => ['—', '—', '—', 'Browser-only.'],
    'uuid' => ['/api/uuid', 'GET', 'count', 'UI uses browser crypto. Optional API for scripts.'],
    'qr' => ['—', '—', '—', 'Browser-only; external QR image service is called only on Generate.'],
    'regex' => ['—', '—', '—', 'Browser-only.'],
    'base64' => ['/api/base64', 'GET', 'str, mode', 'UI encodes/decodes locally. API mode is encode or decode.'],
    'jwt' => ['—', '—', '—', 'Browser-only.'],
    'user-agent' => ['/api/user-agent', 'GET', 'ua', 'UI parses locally. API can use the request header or an explicit ua parameter.'],
    'markdown' => ['—', '—', '—', 'Browser-only.'],
    'ip' => ['/api/ip', 'GET', '—', 'Returns server-observed IPv4/IPv6 for this connection.'],
    'secret' => ['/api/secret', 'GET', 'length, format, count', 'UI uses Web Crypto. Optional API for scripts; nothing is stored.'],
    'encryption' => ['/api/encryption', 'GET', 'str, key, mode', 'UI uses Web Crypto AES-256-GCM. API mode is encrypt or decrypt.'],
];

$examples = [
    'password' => '/api/password?length=24&upper=1&lower=1&numbers=1&symbols=1',
    'hash' => '/api/hash?str=admin123&algorithm=sha256',
    'timestamp' => '/api/timestamp?timestamp=1755000000&unit=s',
    'uuid' => '/api/uuid?count=1',
    'base64' => '/api/base64?str=hello&mode=encode',
    'user-agent' => '/api/user-agent',
    'ip' => '/api/ip',
    'secret' => '/api/secret?length=48&format=hex',
    'encryption' => '/api/encryption?str=hello&key=change-me&mode=encrypt',
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
$btn = 'inline-flex min-h-11 w-full shrink-0 touch-manipulation cursor-pointer items-center justify-center rounded-xl border border-line bg-white px-4 py-2.5 text-sm font-semibold text-ink transition hover:border-leaf/40 hover:bg-soft disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto';
$btnPrimary = 'inline-flex min-h-11 w-full shrink-0 touch-manipulation cursor-pointer items-center justify-center rounded-xl bg-ink px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-ink/90 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto';
$label = 'mb-2 block text-sm font-semibold text-ink';
$hint = 'mt-3 text-xs leading-relaxed text-muted';
$result = 'mt-4 flex flex-col items-stretch gap-3 rounded-2xl border border-line bg-soft p-4 sm:flex-row sm:items-start';
$resultBody = 'min-h-12 min-w-0 flex-1 overflow-x-auto whitespace-pre-wrap break-words font-mono text-sm text-ink';
$iconBtn = 'inline-flex size-10 touch-manipulation items-center justify-center rounded-xl text-muted transition hover:bg-white/80 hover:text-ink';
$iconSvg = 'size-5 fill-none stroke-current [stroke-linecap:round] [stroke-linejoin:round] [stroke-width:1.8]';
$stat = 'rounded-2xl border border-[#dbe8ce] bg-moss px-4 py-4 text-left sm:px-5';
$check = 'inline-flex min-h-11 items-center gap-2 px-1 text-sm text-ink';

function apiDocs(string $page, array $meta, array $examples): void
{
    [$endpoint, $method, $params, $note] = $meta;
    $wide = $page === 'markdown' ? 'max-w-6xl' : 'max-w-3xl';

    echo '<details class="group mx-auto mt-4 ' . $wide . ' overflow-hidden rounded-2xl border border-line bg-white/80 open:shadow-sm">';
    echo '<summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-4 text-sm font-extrabold text-ink marker:content-none sm:px-5 [&::-webkit-details-marker]:hidden">';
    echo '<span>How to use this tool as an API</span>';
    echo '<span class="text-muted transition group-open:hidden" aria-hidden="true">+</span>';
    echo '<span class="hidden text-muted group-open:inline" aria-hidden="true">−</span>';
    echo '</summary>';
    echo '<div class="space-y-3 border-t border-line px-4 py-4 sm:px-5">';
    echo '<p class="text-xs leading-relaxed text-muted">' . esc($note) . '</p>';

    if ($endpoint !== '—') {
        echo '<div><p class="mb-1 text-xs font-bold uppercase tracking-wide text-muted">Endpoint</p><code class="block overflow-x-auto rounded-xl border border-line bg-soft px-3 py-2.5 font-mono text-xs text-ink">' . esc($method . ' ' . $endpoint) . '</code></div>';
        echo '<div><p class="mb-1 text-xs font-bold uppercase tracking-wide text-muted">Parameters</p><code class="block overflow-x-auto rounded-xl border border-line bg-soft px-3 py-2.5 font-mono text-xs text-ink">' . esc($params) . '</code></div>';
        echo '<div><p class="mb-1 text-xs font-bold uppercase tracking-wide text-muted">Example</p><code class="block overflow-x-auto rounded-xl border border-line bg-soft px-3 py-2.5 font-mono text-xs text-ink">' . esc($examples[$page] ?? '') . '</code></div>';
        echo '<p class="text-xs leading-relaxed text-muted">JSON response. Avoid putting passwords or sensitive secrets in query strings because URLs can be logged.</p>';
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
        <?php foreach ($tools as [$slug, $name, $desc, $icon]): ?>
          <a class="flex items-start gap-3 rounded-xl px-3 py-2.5 text-left transition hover:bg-soft" data-search-item data-search="<?= esc($name . ' ' . $desc . ' ' . $slug) ?>" href="/<?= esc($slug) ?>">
            <span class="mt-0.5 grid size-9 shrink-0 place-items-center rounded-lg bg-moss text-[10px] font-extrabold tracking-wide text-ink"><?= esc($icon) ?></span>
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
      <?php foreach ($tools as [$slug, $name, $desc, $icon]): ?>
        <a class="group flex h-full items-start gap-3 rounded-2xl border border-line bg-white/80 p-4 text-left transition hover:border-leaf/30 hover:shadow-lg hover:shadow-ink/5 motion-safe:hover:-translate-y-0.5 sm:gap-4 sm:p-5" href="/<?= esc($slug) ?>">
          <span class="mt-0.5 grid size-11 shrink-0 place-items-center rounded-xl bg-moss text-xs font-extrabold tracking-wide text-ink"><?= esc($icon) ?></span>
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
      <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl"><?= esc($toolName) ?></h1>
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
      <p class="<?= $hint ?>">Generated in your browser. The optional API is for scripts and does not store passwords.</p>

<?php elseif ($page === 'hash'): ?>
      <label class="<?= $label ?>" for="input">Text</label>
      <textarea id="input" rows="6" class="<?= $field ?>" placeholder="admin123" spellcheck="false"></textarea>
      <div class="mt-3 flex flex-col gap-2 sm:flex-row">
        <select id="algorithm" class="<?= $field ?> flex-1">
          <option value="sha256">SHA-256</option>
          <option value="sha384">SHA-384</option>
          <option value="sha512">SHA-512</option>
          <option value="md5">MD5</option>
          <option value="sha1">SHA-1</option>
          <option value="bcrypt">bcrypt</option>
          <option value="all">All supported hashes</option>
        </select>
        <input id="cost" type="number" min="4" max="31" value="12" title="bcrypt cost" aria-label="bcrypt cost" class="<?= $field ?> hidden sm:max-w-28">
        <button class="<?= $btnPrimary ?>" id="run" type="button">Hash</button>
      </div>
      <p class="<?= $hint ?>">SHA-256/384/512 run in your browser. MD5, SHA-1, bcrypt and All use the server API. Press Ctrl+Enter to hash.</p>
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
      <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
        <select id="unit" class="<?= $field ?>">
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
      <p class="<?= $hint ?>">Generated locally with the browser cryptographic random source.</p>

<?php elseif ($page === 'qr'): ?>
      <label class="<?= $label ?>" for="input">Text or URL</label>
      <input id="input" placeholder="https://example.com" class="<?= $field ?>" autocomplete="off" spellcheck="false">
      <div id="qr" class="mt-4 grid min-h-48 place-items-center rounded-2xl border border-dashed border-line bg-panel px-4 text-center text-sm text-muted sm:min-h-56">Enter text, then generate</div>
      <button class="<?= $btnPrimary ?> mt-4" id="run" type="button">Generate QR</button>
      <p class="<?= $hint ?>">Uses the public QR image endpoint only when you click Generate.</p>

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
      <p class="<?= $hint ?>">Decoding does not verify the signature.</p>

<?php elseif ($page === 'user-agent'): ?>
      <label class="<?= $label ?>" for="input">User-Agent string</label>
      <textarea id="input" rows="4" placeholder="Edit to re-parse instantly" class="<?= $field ?>" spellcheck="false"></textarea>
      <div class="mt-4 grid gap-3 sm:grid-cols-2" id="uaCards"></div>
      <div class="<?= $result ?>">
        <pre id="uaOutput" class="<?= $resultBody ?>" aria-live="polite"></pre>
        <button id="copy" type="button" class="<?= $btn ?>">Copy</button>
      </div>
      <p class="<?= $hint ?>">Parses locally as you type. Starts with this browser’s User-Agent.</p>

<?php elseif ($page === 'markdown'): ?>
      <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <strong class="block text-sm font-bold text-ink">Markdown Preview</strong>
          <span class="text-xs text-muted" id="mdStats">0 characters</span>
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
      <p class="<?= $hint ?>">A single TCP connection is either IPv4 or IPv6. The unused family shows as unavailable for this request.</p>

<?php elseif ($page === 'secret'): ?>
      <div class="flex items-center justify-between gap-3">
        <label class="<?= $label ?> mb-0" for="secretLen">Length</label>
        <output id="secretLenOut" class="font-mono text-sm text-muted" for="secretLen">48</output>
      </div>
      <input id="secretLen" class="mt-1" type="range" min="16" max="256" value="48">
      <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
        <select id="secretFormat" class="<?= $field ?>">
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
      <p class="<?= $hint ?>">Generated locally with the Web Crypto API.</p>

<?php elseif ($page === 'encryption'): ?>
      <label class="<?= $label ?>" for="input">String</label>
      <textarea id="input" rows="5" placeholder="Hello world" class="<?= $field ?>" spellcheck="false"></textarea>
      <label class="<?= $label ?> mt-4" for="key">Secret key</label>
      <input id="key" value="secret-key" autocomplete="off" class="<?= $field ?>" spellcheck="false">
      <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
        <select id="mode" class="<?= $field ?>">
          <option value="encrypt">Encrypt</option>
          <option value="decrypt">Decrypt</option>
        </select>
        <button class="<?= $btnPrimary ?>" id="run" type="button">Run</button>
      </div>
      <div class="<?= $result ?>">
        <pre id="output" class="<?= $resultBody ?>" aria-live="polite"></pre>
        <button id="copy" type="button" class="<?= $btn ?>">Copy</button>
      </div>
      <p class="<?= $hint ?>">AES-256-GCM with PBKDF2, in your browser. Press Ctrl+Enter to run.</p>
<?php endif; ?>
    </section>
    <?php apiDocs($page, $meta[$page], $examples); ?>
<?php endif; ?>
  </main>

  <footer class="mx-auto w-full max-w-6xl border-t border-line px-4 py-5 text-xs text-muted sm:px-5">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <span>tools.rajujha.dev</span>
      <span>No analytics · No accounts · Client-first</span>
    </div>
  </footer>
  <div id="toast" class="pointer-events-none fixed bottom-[max(1.25rem,env(safe-area-inset-bottom))] left-1/2 z-40 -translate-x-1/2 rounded-full bg-ink px-4 py-2 text-sm font-semibold text-white opacity-0 shadow-lg transition aria-hidden:opacity-0" role="status" aria-live="polite" aria-hidden="true"></div>
  <script src="/assets/app.js?v=<?= esc($jsVersion) ?>" defer></script>
</body>
</html>
