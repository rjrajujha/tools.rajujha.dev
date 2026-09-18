      <div class="<?= $stat ?>">
        <span class="text-xs text-muted">Primary observed IP</span>
        <strong id="ipOutput" class="mt-1 block break-all font-mono text-xl font-bold tracking-tight sm:text-2xl">Checking…</strong>
        <small id="ipVersion" class="mt-1 block text-xs text-muted">Read from your request</small>
      </div>
      <div class="<?= $result ?>">
        <pre id="ipDetails" class="<?= $resultBody ?>" aria-live="polite"></pre>
        <button id="copy" type="button" class="<?= $btn ?>">Copy</button>
      </div>
      <button class="<?= $btnPrimary ?> mt-4" id="run" type="button">Refresh IP</button>
      <p class="<?= $hint ?>">Shows the server-observed address for this connection. Proxy headers are not trusted.</p>
