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
