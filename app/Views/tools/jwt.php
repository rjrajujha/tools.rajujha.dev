      <label class="<?= $label ?>" for="input">JWT</label>
      <textarea id="input" rows="6" placeholder="Paste a JWT here" autocomplete="off" class="<?= $field ?>" spellcheck="false"></textarea>
      <button class="<?= $btnPrimary ?> mt-3" id="run" type="button">Decode JWT</button>
      <div class="mt-4 grid gap-3 md:grid-cols-2">
        <div class="rounded-2xl border border-line bg-soft p-4">
          <strong class="mb-2 block text-xs font-bold uppercase tracking-wide text-muted">Header</strong>
          <pre id="headerOut" class="overflow-x-auto whitespace-pre-wrap break-all font-mono text-sm"></pre>
        </div>
        <div class="rounded-2xl border border-line bg-soft p-4">
          <strong class="mb-2 block text-xs font-bold uppercase tracking-wide text-muted">Payload</strong>
          <pre id="payloadOut" class="overflow-x-auto whitespace-pre-wrap break-all font-mono text-sm"></pre>
        </div>
      </div>
      <p class="<?= $hint ?>">Paste a token, then decode. Signature is not verified.</p>
