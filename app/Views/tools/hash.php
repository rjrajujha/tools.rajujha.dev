      <label class="<?= $label ?>" for="input">Text</label>
      <textarea id="input" rows="6" class="<?= $field ?>" placeholder="admin123" spellcheck="false"></textarea>
      <label class="<?= $label ?> mt-4" for="algorithm">Algorithm</label>
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
        <select id="algorithm" class="<?= $controlSelect ?> sm:min-w-0 sm:flex-1">
          <option value="sha256">SHA-256</option>
          <option value="sha384">SHA-384</option>
          <option value="sha512">SHA-512</option>
          <option value="md5">MD5</option>
          <option value="sha1">SHA-1</option>
          <option value="bcrypt">bcrypt</option>
          <option value="all">All supported hashes</option>
        </select>
        <input id="cost" type="number" min="<?= (int) APP_BCRYPT_COST_MIN ?>" max="<?= (int) $maxBcryptCost ?>" value="<?= (int) $bcryptCost ?>" title="bcrypt cost" aria-label="bcrypt cost" class="<?= $controlSelect ?> hidden sm:w-28">
        <button class="<?= $btnPrimary ?>" id="run" type="button">Hash</button>
      </div>
      <p class="<?= $hint ?>">Enter text, pick an algorithm, then hash. SHA-256/384/512 stay local; MD5, SHA-1, bcrypt, and All use POST. Ctrl+Enter to run.</p>
      <div class="<?= $result ?>">
        <pre id="output" class="<?= $resultBody ?>" aria-live="polite"></pre>
        <button id="copy" type="button" class="<?= $btn ?>">Copy</button>
      </div>
