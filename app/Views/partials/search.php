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
