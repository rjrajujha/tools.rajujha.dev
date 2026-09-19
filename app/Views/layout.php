<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="description" content="<?= esc($description) ?>">
  <meta name="theme-color" content="#f3f6f0">
  <meta name="color-scheme" content="light dark">
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
  <script src="/assets/theme.js?v=<?= esc($themeJsVersion) ?>"></script>
  <link rel="stylesheet" href="/assets/app.css?v=<?= esc($cssVersion) ?>">
</head>
<body class="flex min-h-dvh flex-col overflow-x-hidden">
  <a class="absolute left-4 top-0 z-50 -translate-y-full rounded-b-xl bg-ink px-4 py-2 text-sm font-semibold text-inverse focus:translate-y-0" href="#main">Skip to content</a>
  <?php \App\View::render('partials/header', get_defined_vars()); ?>
  <?php \App\View::render('partials/search', get_defined_vars()); ?>

  <main id="main" class="mx-auto w-full min-w-0 max-w-6xl flex-1 px-4 py-8 sm:px-5 sm:py-12 lg:py-16">
<?php if ($page === 'home'): ?>
    <?php \App\View::render('home', get_defined_vars()); ?>
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

    <section class="<?= $page === 'markdown' ? 'max-w-6xl' : 'max-w-3xl' ?> mx-auto min-w-0 overflow-x-hidden rounded-2xl border border-line bg-card p-4 shadow-xl shadow-ink/5 sm:rounded-3xl sm:p-7 lg:p-8" data-tool="<?= esc($page) ?>">
      <?php \App\View::render('tools/' . $page, get_defined_vars()); ?>
    </section>
    <?php \App\View::render('partials/api-docs', get_defined_vars()); ?>
<?php endif; ?>
  </main>

  <?php \App\View::render('partials/footer', get_defined_vars()); ?>
  <div id="toast" class="pointer-events-none fixed bottom-[max(1.25rem,env(safe-area-inset-bottom))] left-1/2 z-40 -translate-x-1/2 rounded-full bg-ink px-4 py-2 text-sm font-semibold text-inverse opacity-0 shadow-lg transition aria-hidden:opacity-0" role="status" aria-live="polite" aria-hidden="true"></div>
  <script type="application/json" id="app-config"><?= json_encode(public_client_config(), JSON_UNESCAPED_SLASHES) ?></script>
  <?php if ($page === 'qr'): ?>
  <script src="/assets/vendor/qrcode-generator.js?v=<?= esc((string) @filemtime(APP_ROOT . '/assets/vendor/qrcode-generator.js')) ?>" defer></script>
  <script src="/assets/vendor/qrcode-generator-utf8.js?v=<?= esc((string) @filemtime(APP_ROOT . '/assets/vendor/qrcode-generator-utf8.js')) ?>" defer></script>
  <?php endif; ?>
  <script src="/assets/app.js?v=<?= esc($jsVersion) ?>" defer></script>
</body>
</html>
