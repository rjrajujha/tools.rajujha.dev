      <label class="<?= $label ?>" for="input">String</label>
      <textarea id="input" rows="5" class="<?= $field ?>" placeholder="admin123" spellcheck="false"></textarea>
      <label class="<?= $label ?> mt-4" for="hashValue">Hash</label>
      <textarea id="hashValue" rows="3" class="<?= $field ?>" placeholder="Paste a hex digest or bcrypt hash" spellcheck="false"></textarea>
      <label class="<?= $label ?> mt-4" for="algorithm">Algorithm</label>
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
        <select id="algorithm" class="<?= $controlSelect ?> sm:min-w-0 sm:flex-1">
          <option value="auto" selected>Auto</option>
          <option value="sha256">SHA-256</option>
          <option value="sha384">SHA-384</option>
          <option value="sha512">SHA-512</option>
          <option value="sha1">SHA-1</option>
          <option value="md5">MD5</option>
          <option value="bcrypt">bcrypt</option>
        </select>
        <button class="<?= $btnPrimary ?>" id="run" type="button">Validate</button>
      </div>
      <p class="<?= $hint ?>">Enter a string and hash, pick an algorithm, then validate. SHA and MD5 stay local; bcrypt uses POST. Ctrl+Enter to run.</p>
      <div class="<?= $result ?>">
        <pre id="output" class="<?= $resultBody ?>" aria-live="polite"></pre>
        <button id="copy" type="button" class="<?= $btn ?>">Copy</button>
      </div>
