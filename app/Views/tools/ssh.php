      <label class="<?= $label ?>" for="sshAlgorithm">Algorithm</label>
      <select id="sshAlgorithm" class="<?= $controlSelect ?>">
        <option value="ed25519" selected>Ed25519</option>
        <option value="rsa2048">RSA 2048</option>
        <option value="rsa4096">RSA 4096</option>
      </select>
      <label class="<?= $label ?> mt-4" for="sshComment">Comment</label>
      <input id="sshComment" class="<?= $field ?>" autocomplete="off" spellcheck="false" placeholder="optional comment">
      <label class="<?= $label ?> mt-4" for="sshPassphrase">Passphrase</label>
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
        <input id="sshPassphrase" class="<?= $field ?>" type="password" autocomplete="new-password" spellcheck="false" placeholder="optional passphrase">
        <button type="button" id="sshPassphraseToggle" class="<?= $btn ?>" aria-pressed="false">Show</button>
      </div>
      <button class="<?= $btnPrimary ?> mt-4" id="run" type="button">Generate key pair</button>
      <div id="sshOutputs" class="mt-4 space-y-3" hidden>
        <article class="rounded-2xl border border-line bg-soft p-4">
          <h2 class="text-sm font-extrabold text-ink">Public key</h2>
          <pre id="sshPublic" class="mt-3 min-h-12 overflow-x-auto whitespace-pre-wrap break-all font-mono text-sm text-ink" aria-live="polite"></pre>
          <div class="mt-3 flex flex-col gap-2 sm:flex-row">
            <button id="copyPublic" type="button" class="<?= $btn ?>">Copy</button>
            <button id="downloadPublic" type="button" class="<?= $btn ?>">Download</button>
          </div>
        </article>
        <article class="rounded-2xl border border-line bg-soft p-4">
          <h2 class="text-sm font-extrabold text-ink">Private key</h2>
          <pre id="sshPrivate" class="mt-3 min-h-12 overflow-x-auto whitespace-pre-wrap break-all font-mono text-sm text-ink" aria-live="polite"></pre>
          <div class="mt-3 flex flex-col gap-2 sm:flex-row">
            <button id="copyPrivate" type="button" class="<?= $btn ?>">Copy</button>
            <button id="downloadPrivate" type="button" class="<?= $btn ?>">Download</button>
          </div>
        </article>
      </div>
      <div id="sshError" class="<?= $result ?>" hidden role="alert">
        <pre id="output" class="<?= $resultBody ?> text-ink" aria-live="polite"></pre>
      </div>
      <p class="<?= $hint ?>">Keys are created in your browser when Web Crypto supports the algorithm. A passphrase uses the API fallback so the private key can be encrypted. Keys and passphrases are not stored.</p>
