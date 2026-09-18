      <p class="<?= $label ?>" id="qrTypeLabel">QR type</p>
      <input type="hidden" id="qrType" value="text">
      <div class="flex flex-wrap gap-2" role="radiogroup" aria-labelledby="qrTypeLabel">
        <?php
        $qrTypes = [
            'text' => 'Text',
            'contact' => 'Contact',
            'email' => 'Email',
            'wifi' => 'WiFi',
            'upi' => 'Bank UPI',
            'ccupi' => 'CC UPI',
        ];
        foreach ($qrTypes as $value => $qrLabel):
            $active = $value === 'text';
        ?>
          <button
            type="button"
            class="<?= $active ? $btnPrimary : $btn ?> sm:w-auto"
            role="radio"
            aria-checked="<?= $active ? 'true' : 'false' ?>"
            tabindex="<?= $active ? '0' : '-1' ?>"
            data-qr-type="<?= esc($value) ?>"
          ><?= esc($qrLabel) ?></button>
        <?php endforeach; ?>
      </div>

      <div id="qrPanelText" class="mt-4">
        <label class="<?= $label ?>" for="input">Text or URL</label>
        <textarea id="input" rows="4" placeholder="https://example.com" class="<?= $field ?>" autocomplete="off" spellcheck="false"></textarea>
        <p class="mt-1 text-xs text-muted" data-error-for="input" hidden></p>
      </div>

      <div id="qrPanelContact" class="mt-4 space-y-4" hidden>
        <div class="grid gap-3 sm:grid-cols-2">
          <div>
            <label class="<?= $label ?>" for="qrFirstName">First name</label>
            <input id="qrFirstName" class="<?= $field ?>" autocomplete="off" spellcheck="false" placeholder="John">
            <p class="mt-1 text-xs text-muted" data-error-for="qrFirstName" hidden></p>
          </div>
          <div>
            <label class="<?= $label ?>" for="qrLastName">Last name</label>
            <input id="qrLastName" class="<?= $field ?>" autocomplete="off" spellcheck="false" placeholder="Doe">
          </div>
        </div>
        <div>
          <label class="<?= $label ?>" for="qrPhone">Phone</label>
          <input id="qrPhone" class="<?= $field ?>" autocomplete="off" spellcheck="false" placeholder="+15551234567">
        </div>
        <div>
          <label class="<?= $label ?>" for="qrContactEmail">Email</label>
          <input id="qrContactEmail" class="<?= $field ?>" autocomplete="off" spellcheck="false" placeholder="john.doe@example.com">
        </div>
        <div>
          <label class="<?= $label ?>" for="qrWebsite">Website</label>
          <input id="qrWebsite" class="<?= $field ?>" autocomplete="off" spellcheck="false" placeholder="https://example.com">
        </div>
      </div>

      <div id="qrPanelEmail" class="mt-4 space-y-4" hidden>
        <div>
          <label class="<?= $label ?>" for="qrMailTo">Email</label>
          <input id="qrMailTo" class="<?= $field ?>" autocomplete="off" spellcheck="false" placeholder="john@example.com">
          <p class="mt-1 text-xs text-muted" data-error-for="qrMailTo" hidden></p>
        </div>
        <div>
          <label class="<?= $label ?>" for="qrSubject">Subject</label>
          <input id="qrSubject" class="<?= $field ?>" autocomplete="off" spellcheck="false" placeholder="Hello">
        </div>
        <div>
          <label class="<?= $label ?>" for="qrBody">Body</label>
          <textarea id="qrBody" rows="4" class="<?= $field ?>" autocomplete="off" spellcheck="false" placeholder="Hi"></textarea>
        </div>
      </div>

      <div id="qrPanelWifi" class="mt-4 space-y-4" hidden>
        <div>
          <label class="<?= $label ?>" for="qrWifiSsid">SSID</label>
          <input id="qrWifiSsid" class="<?= $field ?>" autocomplete="off" spellcheck="false" placeholder="MyWifi">
          <p class="mt-1 text-xs text-muted" data-error-for="qrWifiSsid" hidden></p>
        </div>
        <div>
          <label class="<?= $label ?>" for="qrWifiPassword">Password</label>
          <input id="qrWifiPassword" class="<?= $field ?>" autocomplete="off" spellcheck="false" placeholder="Optional">
        </div>
        <div>
          <label class="<?= $label ?>" for="qrWifiSecurity">Security</label>
          <select id="qrWifiSecurity" class="<?= $controlSelect ?>">
            <option value="WPA" selected>WPA/WPA2</option>
            <option value="WEP">WEP</option>
            <option value="nopass">None</option>
          </select>
        </div>
        <label class="<?= $check ?>"><input id="qrWifiHidden" type="checkbox" class="size-4 accent-ink"> Hidden network</label>
      </div>

      <div id="qrPanelUpi" class="mt-4 space-y-4" hidden>
        <div>
          <label class="<?= $label ?>" for="qrUpiMode">Payment address type</label>
          <select id="qrUpiMode" class="<?= $controlSelect ?>">
            <option value="vpa" selected>UPI ID (VPA)</option>
            <option value="account">Account number + IFSC</option>
          </select>
        </div>
        <div id="qrUpiVpaFields">
          <label class="<?= $label ?>" for="qrUpiId">UPI ID</label>
          <input id="qrUpiId" class="<?= $field ?>" autocomplete="off" spellcheck="false" placeholder="johndoe@sbi">
          <p class="mt-1 text-xs text-muted" data-error-for="qrUpiId" hidden></p>
        </div>
        <div id="qrUpiAccountFields" class="space-y-4" hidden>
          <div>
            <label class="<?= $label ?>" for="qrAccount">Account number</label>
            <input id="qrAccount" class="<?= $field ?>" inputmode="numeric" autocomplete="off" spellcheck="false" placeholder="1234567890">
            <p class="mt-1 text-xs text-muted" data-error-for="qrAccount" hidden></p>
          </div>
          <div>
            <label class="<?= $label ?>" for="qrIfsc">IFSC</label>
            <input id="qrIfsc" class="<?= $field ?>" autocomplete="off" spellcheck="false" placeholder="SBIN0001234">
            <p class="mt-1 text-xs text-muted" data-error-for="qrIfsc" hidden></p>
          </div>
        </div>
        <div>
          <label class="<?= $label ?>" for="qrUpiName">Name</label>
          <input id="qrUpiName" class="<?= $field ?>" autocomplete="off" spellcheck="false" placeholder="John Doe">
        </div>
        <div>
          <label class="<?= $label ?>" for="qrUpiAmount">Amount</label>
          <input id="qrUpiAmount" class="<?= $field ?>" inputmode="decimal" autocomplete="off" spellcheck="false" placeholder="123.45">
          <p class="mt-1 text-xs text-muted" data-error-for="qrUpiAmount" hidden></p>
        </div>
      </div>

      <div id="qrPanelCcupi" class="mt-4 space-y-4" hidden>
        <div>
          <label class="<?= $label ?>" for="qrCcBank">Bank</label>
          <select id="qrCcBank" class="<?= $controlSelect ?>">
            <option value="sbi" selected>SBI</option>
            <option value="axis">Axis</option>
            <option value="icici">ICICI</option>
            <option value="idfc">IDFC</option>
            <option value="au">AU Bank</option>
            <option value="amex">Amex</option>
          </select>
        </div>
        <div id="qrCcCardFields">
          <label class="<?= $label ?>" for="qrCcCard">Card number</label>
          <input id="qrCcCard" class="<?= $field ?>" inputmode="numeric" autocomplete="off" spellcheck="false" placeholder="16-digit card number">
          <p class="mt-1 text-xs text-muted" data-error-for="qrCcCard" hidden></p>
        </div>
        <div id="qrCcMobileFields" class="space-y-4" hidden>
          <div>
            <label class="<?= $label ?>" for="qrCcMobile">Mobile number</label>
            <input id="qrCcMobile" class="<?= $field ?>" inputmode="numeric" autocomplete="off" spellcheck="false" placeholder="10-digit mobile">
            <p class="mt-1 text-xs text-muted" data-error-for="qrCcMobile" hidden></p>
          </div>
          <div>
            <label class="<?= $label ?>" for="qrCcLast4">Last 4 card digits</label>
            <input id="qrCcLast4" class="<?= $field ?>" inputmode="numeric" autocomplete="off" spellcheck="false" placeholder="1234" maxlength="4">
            <p class="mt-1 text-xs text-muted" data-error-for="qrCcLast4" hidden></p>
          </div>
        </div>
      </div>

      <p id="qrStatus" class="mt-3 text-xs leading-relaxed text-muted"></p>
      <div class="mt-4 flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
        <p class="mb-0 min-w-0 text-sm font-semibold text-ink" id="qrLevelLabel">Error correction</p>
        <input type="hidden" id="qrLevel" value="M">
        <div class="ml-auto flex shrink-0 gap-2" role="radiogroup" aria-labelledby="qrLevelLabel">
          <?php foreach (['L', 'M', 'Q', 'H'] as $level):
              $active = $level === 'M';
          ?>
            <button
              type="button"
              class="<?= $active ? $btnPrimary : $chip ?> !w-11"
              role="radio"
              aria-checked="<?= $active ? 'true' : 'false' ?>"
              tabindex="<?= $active ? '0' : '-1' ?>"
              data-qr-level="<?= esc($level) ?>"
              title="<?= esc($level) ?> error correction"
            ><?= esc($level) ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div id="qr" class="mt-4 grid min-h-48 place-items-center rounded-2xl border border-dashed border-line bg-panel px-4 text-center text-sm text-muted sm:min-h-56">Enter text, then generate</div>
      <div class="mt-4 flex flex-col gap-2 sm:flex-row">
        <button class="<?= $btnPrimary ?> sm:flex-1" id="run" type="button" disabled>Generate QR</button>
        <button id="qrDownloadPng" type="button" class="<?= $btn ?> sm:flex-1" disabled>Download PNG</button>
        <button id="qrDownloadSvg" type="button" class="<?= $btn ?> sm:flex-1" disabled>Download SVG</button>
      </div>
      <p class="<?= $hint ?>">Choose a type, fill the required fields, then generate. Stays in your browser.</p>
