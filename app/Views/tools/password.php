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
