      <label class="<?= $label ?>" for="input">User-Agent string</label>
      <textarea id="input" rows="4" placeholder="Edit to re-parse instantly" class="<?= $field ?>" spellcheck="false"></textarea>
      <div class="mt-4 grid gap-3 sm:grid-cols-2" id="uaCards"></div>
      <div class="<?= $result ?>">
        <pre id="uaOutput" class="<?= $resultBody ?>" aria-live="polite"></pre>
        <button id="copy" type="button" class="<?= $btn ?>">Copy</button>
      </div>
      <p class="<?= $hint ?>">Edit the string to re-parse. Starts with this browser’s User-Agent.</p>
