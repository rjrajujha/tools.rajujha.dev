      <div class="<?= $stat ?> mb-5">
        <span class="text-xs text-muted">Current UTC</span>
        <strong id="currentTimestamp" class="mt-1 block font-mono text-xl font-bold tracking-tight sm:text-2xl">Loading…</strong>
        <small id="currentUtc" class="mt-1 block text-xs text-muted"></small>
      </div>
      <label class="<?= $label ?>" for="input">Unix timestamp</label>
      <input id="input" inputmode="decimal" placeholder="1755000000" class="<?= $field ?>" autocomplete="off">
      <label class="<?= $label ?> mt-4" for="unit">Unit</label>
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
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
