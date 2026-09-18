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
