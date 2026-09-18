<?php
[$endpoint, $method, $params, $note] = $meta[$page];
$wide = $page === 'markdown' ? 'max-w-6xl' : 'max-w-3xl';
$hasApi = is_string($endpoint) && $endpoint !== '';
$example = $hasApi ? (string) ($examples[$page] ?? '') : '';
$exampleId = 'apiExample-' . preg_replace('/[^a-z0-9-]+/', '', $page);
?>
<details class="group mx-auto mt-4 <?= $wide ?> overflow-hidden rounded-2xl border border-line bg-white/80 open:shadow-sm">
  <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-4 text-sm font-extrabold text-ink marker:content-none sm:px-5 [&::-webkit-details-marker]:hidden">
    <span>How to use this tool as an API</span>
    <span class="text-muted transition group-open:hidden" aria-hidden="true">+</span>
    <span class="hidden text-muted group-open:inline" aria-hidden="true">−</span>
  </summary>
  <div class="space-y-3 border-t border-line px-4 py-4 sm:px-5">
    <p class="text-xs leading-relaxed text-muted"><?= esc($note) ?></p>
    <?php if ($hasApi): ?>
      <code class="block overflow-x-auto rounded-xl border border-line bg-soft px-3 py-2.5 font-mono text-xs text-ink"><?= esc(trim((string) $method . ' ' . $endpoint)) ?></code>
      <?php if (is_string($params) && $params !== '' && $params !== '—'): ?>
        <p class="text-xs text-muted">Params: <code class="font-mono text-ink"><?= esc($params) ?></code></p>
      <?php endif; ?>
      <?php if ($example !== ''): ?>
        <div>
          <p class="mb-1 text-xs font-bold uppercase tracking-wide text-muted">Example</p>
          <div class="relative rounded-xl border border-line bg-soft">
            <button type="button" class="absolute right-2 top-2 z-10 inline-flex size-11 shrink-0 touch-manipulation cursor-pointer items-center justify-center rounded-xl border border-line bg-white text-muted shadow-sm transition hover:border-leaf/50 hover:bg-moss/80 hover:text-ink active:scale-[0.98] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-leaf motion-reduce:transition-none motion-reduce:active:scale-100" data-copy-target="#<?= esc($exampleId) ?>" data-copy-mode="icon" aria-label="Copy example">
              <svg class="<?= esc($iconSvg) ?> size-4" viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
            </button>
            <code id="<?= esc($exampleId) ?>" class="block overflow-x-auto whitespace-pre-wrap pb-3 pl-4 pr-14 pt-14 font-mono text-xs leading-relaxed text-ink"><?= esc($example) ?></code>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</details>
