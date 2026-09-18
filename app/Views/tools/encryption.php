      <label class="<?= $label ?>" for="input" id="inputLabel">String / Input</label>
      <textarea id="input" rows="5" placeholder="Hello world" class="<?= $field ?>" spellcheck="false"></textarea>
      <label class="<?= $label ?> mt-4" for="key">Secret Key</label>
      <input id="key" value="" autocomplete="off" class="<?= $field ?>" spellcheck="false" placeholder="A long random secret">
      <p class="mt-2 text-sm leading-relaxed text-ink">Use <a class="font-semibold text-leaf underline underline-offset-2 hover:text-ink" href="/secret">/secret</a> for a strong key.</p>
      <div class="<?= $controlRow ?> mt-4" role="group" aria-label="Encrypt or decrypt action">
        <select
          id="mode"
          class="<?= $controlSelect ?> sm:w-36"
          autocomplete="off"
          aria-label="Encrypt or decrypt"
        >
          <option value="encrypt" selected>Encrypt</option>
          <option value="decrypt">Decrypt</option>
        </select>
        <button class="<?= $btnPrimary ?> sm:min-w-28 sm:px-5" id="run" type="button" data-label="Encrypt">Encrypt</button>
      </div>

      <div id="encryptOutputs" class="mt-4 space-y-3" hidden>
        <article class="rounded-2xl border border-line bg-soft p-4">
          <h2 class="text-sm font-extrabold text-ink">Compact Encrypted Text</h2>
          <p class="mt-1 text-xs leading-relaxed text-muted">Opaque Base64 representation of the same encryption result.</p>
          <pre id="compactOutput" class="mt-3 min-h-12 overflow-x-auto whitespace-pre-wrap break-all font-mono text-sm text-ink" aria-live="polite"></pre>
          <button id="copyCompact" type="button" class="<?= $btn ?> mt-3">Copy</button>
        </article>
        <article class="rounded-2xl border border-line bg-soft p-4">
          <h2 class="text-sm font-extrabold text-ink">Encrypted JSON / Object</h2>
          <p class="mt-1 text-xs leading-relaxed text-muted">Structured metadata and ciphertext from the same operation.</p>
          <pre id="jsonOutput" class="mt-3 min-h-12 overflow-x-auto whitespace-pre-wrap break-all font-mono text-sm text-ink" aria-live="polite"></pre>
          <button id="copyJson" type="button" class="<?= $btn ?> mt-3">Copy</button>
        </article>
        <p class="text-xs leading-relaxed text-muted">Both cards are one encryption. Base64 is encoding, not encryption. The secret is never included.</p>
      </div>

      <div id="decryptOutputs" class="<?= $result ?>" hidden>
        <div class="min-w-0 flex-1">
          <p class="mb-2 text-xs font-bold uppercase tracking-wide text-muted">Decrypted Text</p>
          <pre id="decryptOutput" class="<?= $resultBody ?> mt-0" aria-live="polite"></pre>
        </div>
        <button id="copyDecrypt" type="button" class="<?= $btn ?>">Copy</button>
      </div>

      <div id="encError" class="<?= $result ?>" hidden role="alert">
        <pre id="errorOutput" class="<?= $resultBody ?> text-ink" aria-live="polite"></pre>
      </div>

      <p class="<?= $hint ?>" id="encHint">Enter text and a secret, choose Encrypt or Decrypt, then run. Stays in your browser. Ctrl+Enter to run.</p>
