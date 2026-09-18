      <div class="flex items-center justify-between gap-3">
        <label class="<?= $label ?> mb-0" for="secretLen">Length</label>
        <output id="secretLenOut" class="font-mono text-sm text-muted" for="secretLen">48</output>
      </div>
      <input id="secretLen" class="mt-1" type="range" min="16" max="256" value="48">
      <label class="<?= $label ?> mt-4" for="secretFormat">Format</label>
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
        <select id="secretFormat" class="<?= $controlSelect ?> sm:min-w-0 sm:flex-1">
          <option value="hex">Hex</option>
          <option value="base64url">Base64URL</option>
          <option value="base64">Base64</option>
        </select>
        <button class="<?= $btnPrimary ?>" id="run" type="button">Generate secret</button>
      </div>
      <div class="<?= $result ?>">
        <code id="secretOutput" class="<?= $resultBody ?>" aria-live="polite"></code>
        <button id="copy" type="button" class="<?= $btn ?>">Copy</button>
      </div>
      <p class="<?= $hint ?>">Choose length and format, then generate. Runs in your browser.</p>
