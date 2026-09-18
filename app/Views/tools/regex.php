      <label class="<?= $label ?>" for="pattern">Regular expression</label>
      <input id="pattern" placeholder="^[A-Z][a-z]+$" class="<?= $field ?>" spellcheck="false" autocomplete="off">
      <label class="<?= $label ?> mt-4" for="input">Test string</label>
      <textarea id="input" rows="5" placeholder="John Doe" class="<?= $field ?>" spellcheck="false"></textarea>
      <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
        <input id="flags" value="g" placeholder="flags" class="<?= $field ?> min-h-11" spellcheck="false" autocomplete="off" aria-label="Regex flags">
        <button class="<?= $btnPrimary ?>" id="run" type="button">Test</button>
      </div>
      <div class="<?= $result ?>">
        <pre id="output" class="<?= $resultBody ?>" aria-live="polite"></pre>
      </div>
      <p class="<?= $hint ?>">Enter a pattern and test string, then run. Executes in a Web Worker.</p>
