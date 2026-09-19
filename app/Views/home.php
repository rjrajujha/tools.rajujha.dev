    <section class="mx-auto max-w-3xl text-center sm:text-left">
      <p class="text-[0.7rem] font-extrabold tracking-[0.18em] text-leaf sm:text-xs">PRIVATE · FAST · OPEN SOURCE</p>
      <h1 class="mt-3 text-[2rem] font-bold tracking-tight text-ink sm:mt-4 sm:text-5xl sm:leading-[0.95] lg:text-6xl">
        Developer tools,<br><span class="text-leaf">without the clutter.</span>
      </h1>
    </section>

    <section class="mt-8 grid grid-cols-1 gap-3 sm:mt-10 sm:grid-cols-2 lg:grid-cols-3" aria-label="Available tools">
      <?php foreach ($tools as [$slug, $name, $desc]): ?>
        <a class="group flex h-full items-start gap-3 rounded-2xl border border-line bg-card/80 p-4 text-left transition hover:border-leaf/30 hover:shadow-lg hover:shadow-ink/5 motion-safe:hover:-translate-y-0.5 sm:gap-4 sm:p-5" href="/<?= esc($slug) ?>">
          <span class="mt-0.5 grid size-11 shrink-0 place-items-center rounded-xl bg-moss text-ink"><?= tool_icon($slug, 'size-5') ?></span>
          <span class="min-w-0 flex-1">
            <h2 class="text-base font-bold leading-snug text-ink group-hover:text-leaf"><?= esc($name) ?></h2>
            <p class="mt-1 line-clamp-2 min-h-10 text-sm leading-5 text-muted"><?= esc($desc) ?></p>
          </span>
        </a>
      <?php endforeach; ?>
    </section>
