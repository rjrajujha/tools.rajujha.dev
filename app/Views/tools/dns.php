      <label class="<?= $label ?>" for="dnsHost">Host</label>
      <input id="dnsHost" class="<?= $field ?>" autocomplete="off" spellcheck="false" placeholder="example.com">
      <label class="<?= $label ?> mt-4" for="dnsType">Type</label>
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
        <select id="dnsType" class="<?= $controlSelect ?> sm:min-w-0 sm:flex-1">
          <option value="A" selected>A</option>
          <option value="AAAA">AAAA</option>
          <option value="MX">MX</option>
          <option value="TXT">TXT</option>
          <option value="CNAME">CNAME</option>
          <option value="NS">NS</option>
        </select>
        <button class="<?= $btnPrimary ?>" id="run" type="button">Lookup</button>
      </div>
      <div class="mt-4 space-y-3" id="dnsCards"></div>
      <div class="<?= $result ?>">
        <pre id="output" class="<?= $resultBody ?>" aria-live="polite"></pre>
        <button id="copy" type="button" class="<?= $btn ?>">Copy</button>
      </div>
      <p class="<?= $hint ?>">Enter a hostname, pick a type, then look up. The browser uses Cloudflare DNS-over-HTTPS first, then Google Public DNS, then this site’s API.</p>
