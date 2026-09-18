(() => {
  const $ = (selector) => document.querySelector(selector);
  const tool = $('[data-tool]')?.dataset.tool;
  const toastEl = $('#toast');
  let toastTimer = 0;

  const LEGACY_PBKDF2_ITERATIONS = 120000;
  const PBKDF2_ITER_MIN = 100000;

  function appConfig() {
    const raw = document.getElementById('app-config')?.textContent;
    if (!raw) throw Error('Missing application config.');
    const parsed = JSON.parse(raw);
    const bcryptCost = Number(parsed.bcryptCost);
    const maxBcryptCost = Number(parsed.maxBcryptCost);
    const encryptionIterations = Number(parsed.encryptionIterations);
    const maxEncryptionIterations = Number(parsed.maxEncryptionIterations);
    if (
      ![bcryptCost, maxBcryptCost, encryptionIterations, maxEncryptionIterations].every(Number.isFinite) ||
      bcryptCost < 4 ||
      maxBcryptCost < bcryptCost ||
      encryptionIterations < PBKDF2_ITER_MIN ||
      maxEncryptionIterations < encryptionIterations
    ) {
      throw Error('Invalid application config.');
    }
    const regexWorker = typeof parsed.regexWorker === 'string' && parsed.regexWorker.startsWith('/assets/regex-worker.js')
      ? parsed.regexWorker
      : '/assets/regex-worker.js';

    return { bcryptCost, maxBcryptCost, encryptionIterations, maxEncryptionIterations, regexWorker };
  }

  function showToast(message) {
    if (!toastEl) return;
    toastEl.textContent = message;
    toastEl.setAttribute('aria-hidden', 'false');
    toastEl.classList.remove('opacity-0');
    toastEl.classList.add('opacity-100');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => {
      toastEl.setAttribute('aria-hidden', 'true');
      toastEl.classList.add('opacity-0');
      toastEl.classList.remove('opacity-100');
    }, 1400);
  }

  async function copyText(value, button = $('#copy')) {
    const text = String(value ?? '');
    if (!text.trim()) {
      showToast('Nothing to copy');
      return;
    }

    try {
      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(text);
      } else {
        throw Error('clipboard unavailable');
      }
    } catch {
      const area = document.createElement('textarea');
      area.value = text;
      area.setAttribute('readonly', '');
      area.className = 'fixed -left-[9999px] top-0';
      document.body.appendChild(area);
      area.select();
      const ok = document.execCommand('copy');
      area.remove();
      if (!ok) {
        showToast('Copy failed');
        return;
      }
    }

    showToast('Copied');
    if (!button) return;

    button.setAttribute('aria-live', 'polite');
    if (button.dataset.copyMode === 'icon') {
      const previous = button.getAttribute('aria-label') || 'Copy';
      button.setAttribute('aria-label', 'Copied');
      button.classList.add('border-leaf/50', 'bg-moss', 'text-ink');
      window.setTimeout(() => {
        button.setAttribute('aria-label', previous);
        button.classList.remove('border-leaf/50', 'bg-moss', 'text-ink');
      }, 1200);
      return;
    }

    const original = button.dataset.label || button.textContent;
    button.dataset.label = original;
    button.textContent = 'Copied';
    button.classList.add('border-leaf/50', 'bg-moss', 'text-ink');
    window.setTimeout(() => {
      button.textContent = original;
      button.classList.remove('border-leaf/50', 'bg-moss', 'text-ink');
    }, 1200);
  }

  function setResult(el, value) {
    if (!el) return;
    el.textContent = value;
    el.classList.remove('result-flash');
    void el.offsetWidth;
    el.classList.add('result-flash');
  }

  function bindSubmit(run, selectors = ['#input', '#pattern', '#flags', '#key', '#unit', '#algorithm', '#cost', '#secretFormat', '#mode']) {
    for (const selector of selectors) {
      const el = $(selector);
      if (!el) continue;
      el.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;
        if (el.tagName === 'TEXTAREA' && !(event.ctrlKey || event.metaKey)) return;
        event.preventDefault();
        run();
      });
    }
  }

  function setBusy(button, busy, label = 'Working…') {
    if (!button) return;
    button.setAttribute('aria-busy', busy ? 'true' : 'false');
    button.classList.toggle('is-loading', Boolean(busy));
    if (busy) {
      button.dataset.label = button.dataset.label || button.textContent;
      button.disabled = true;
      button.textContent = label;
      return;
    }
    button.disabled = false;
    button.textContent = button.dataset.label || button.textContent;
  }

  function getSubtle() {
    try {
      const cryptoObj = globalThis.crypto || globalThis.msCrypto;
      return cryptoObj?.subtle || cryptoObj?.webkitSubtle || null;
    } catch {
      return null;
    }
  }

  function canDigest() {
    return typeof getSubtle()?.digest === 'function';
  }

  function canAesGcm() {
    const subtle = getSubtle();
    return (
      typeof subtle?.importKey === 'function' &&
      typeof subtle?.deriveKey === 'function' &&
      typeof subtle?.encrypt === 'function' &&
      typeof subtle?.decrypt === 'function'
    );
  }

  function utf8Encode(value) {
    return new TextEncoder().encode(value);
  }

  function utf8Decode(bytes) {
    return new TextDecoder().decode(bytes);
  }

  function hex(bytes) {
    return [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');
  }

  function md5Hex(value) {
    return md5Bytes(utf8Encode(value));
  }

  function md5Bytes(bytes) {
    const toHex = (num) => {
      const alphabet = '0123456789abcdef';
      let out = '';
      for (let i = 0; i < 4; i += 1) {
        out += alphabet[(num >> (i * 8 + 4)) & 15] + alphabet[(num >> (i * 8)) & 15];
      }
      return out;
    };
    const add32 = (a, b) => (a + b) | 0;
    const cmn = (q, a, b, x, s, t) => {
      a = add32(add32(a, q), add32(x, t));
      return add32((a << s) | (a >>> (32 - s)), b);
    };
    const ff = (a, b, c, d, x, s, t) => cmn((b & c) | (~b & d), a, b, x, s, t);
    const gg = (a, b, c, d, x, s, t) => cmn((b & d) | (c & ~d), a, b, x, s, t);
    const hh = (a, b, c, d, x, s, t) => cmn(b ^ c ^ d, a, b, x, s, t);
    const ii = (a, b, c, d, x, s, t) => cmn(c ^ (b | ~d), a, b, x, s, t);

    const n = bytes.length;
    const words = [];
    for (let i = 0; i < n; i += 1) {
      words[i >> 2] = (words[i >> 2] || 0) | (bytes[i] << ((i % 4) * 8));
    }
    words[n >> 2] |= 0x80 << ((n % 4) * 8);
    const padded = (((n + 8) >> 6) + 1) * 16;
    while (words.length < padded) words.push(0);
    words[padded - 2] = (n * 8) | 0;
    words[padded - 1] = Math.floor((n * 8) / 4294967296);

    let a = 1732584193;
    let b = -271733879;
    let c = -1732584194;
    let d = 271733878;

    for (let i = 0; i < words.length; i += 16) {
      const oa = a;
      const ob = b;
      const oc = c;
      const od = d;
      a = ff(a, b, c, d, words[i + 0], 7, -680876936);
      d = ff(d, a, b, c, words[i + 1], 12, -389564586);
      c = ff(c, d, a, b, words[i + 2], 17, 606105819);
      b = ff(b, c, d, a, words[i + 3], 22, -1044525330);
      a = ff(a, b, c, d, words[i + 4], 7, -176418897);
      d = ff(d, a, b, c, words[i + 5], 12, 1200080426);
      c = ff(c, d, a, b, words[i + 6], 17, -1473231341);
      b = ff(b, c, d, a, words[i + 7], 22, -45705983);
      a = ff(a, b, c, d, words[i + 8], 7, 1770035416);
      d = ff(d, a, b, c, words[i + 9], 12, -1958414417);
      c = ff(c, d, a, b, words[i + 10], 17, -42063);
      b = ff(b, c, d, a, words[i + 11], 22, -1990404162);
      a = ff(a, b, c, d, words[i + 12], 7, 1804603682);
      d = ff(d, a, b, c, words[i + 13], 12, -40341101);
      c = ff(c, d, a, b, words[i + 14], 17, -1502002290);
      b = ff(b, c, d, a, words[i + 15], 22, 1236535329);
      a = gg(a, b, c, d, words[i + 1], 5, -165796510);
      d = gg(d, a, b, c, words[i + 6], 9, -1069501632);
      c = gg(c, d, a, b, words[i + 11], 14, 643717713);
      b = gg(b, c, d, a, words[i + 0], 20, -373897302);
      a = gg(a, b, c, d, words[i + 5], 5, -701558691);
      d = gg(d, a, b, c, words[i + 10], 9, 38016083);
      c = gg(c, d, a, b, words[i + 15], 14, -660478335);
      b = gg(b, c, d, a, words[i + 4], 20, -405537848);
      a = gg(a, b, c, d, words[i + 9], 5, 568446438);
      d = gg(d, a, b, c, words[i + 14], 9, -1019803690);
      c = gg(c, d, a, b, words[i + 3], 14, -187363961);
      b = gg(b, c, d, a, words[i + 8], 20, 1163531501);
      a = gg(a, b, c, d, words[i + 13], 5, -1444681467);
      d = gg(d, a, b, c, words[i + 2], 9, -51403784);
      c = gg(c, d, a, b, words[i + 7], 14, 1735328473);
      b = gg(b, c, d, a, words[i + 12], 20, -1926607734);
      a = hh(a, b, c, d, words[i + 5], 4, -378558);
      d = hh(d, a, b, c, words[i + 8], 11, -2022574463);
      c = hh(c, d, a, b, words[i + 11], 16, 1839030562);
      b = hh(b, c, d, a, words[i + 14], 23, -35309556);
      a = hh(a, b, c, d, words[i + 1], 4, -1530992060);
      d = hh(d, a, b, c, words[i + 4], 11, 1272893353);
      c = hh(c, d, a, b, words[i + 7], 16, -155497632);
      b = hh(b, c, d, a, words[i + 10], 23, -1094730640);
      a = hh(a, b, c, d, words[i + 13], 4, 681279174);
      d = hh(d, a, b, c, words[i + 0], 11, -358537222);
      c = hh(c, d, a, b, words[i + 3], 16, -722521979);
      b = hh(b, c, d, a, words[i + 6], 23, 76029189);
      a = hh(a, b, c, d, words[i + 9], 4, -640364487);
      d = hh(d, a, b, c, words[i + 12], 11, -421815835);
      c = hh(c, d, a, b, words[i + 15], 16, 530742520);
      b = hh(b, c, d, a, words[i + 2], 23, -995338651);
      a = ii(a, b, c, d, words[i + 0], 6, -198630844);
      d = ii(d, a, b, c, words[i + 7], 10, 1126891415);
      c = ii(c, d, a, b, words[i + 14], 15, -1416354905);
      b = ii(b, c, d, a, words[i + 5], 21, -57434055);
      a = ii(a, b, c, d, words[i + 12], 6, 1700485571);
      d = ii(d, a, b, c, words[i + 3], 10, -1894986606);
      c = ii(c, d, a, b, words[i + 10], 15, -1051523);
      b = ii(b, c, d, a, words[i + 1], 21, -2054922799);
      a = ii(a, b, c, d, words[i + 8], 6, 1873313359);
      d = ii(d, a, b, c, words[i + 15], 10, -30611744);
      c = ii(c, d, a, b, words[i + 6], 15, -1560198380);
      b = ii(b, c, d, a, words[i + 13], 21, 1309151649);
      a = ii(a, b, c, d, words[i + 4], 6, -145523070);
      d = ii(d, a, b, c, words[i + 11], 10, -1120210379);
      c = ii(c, d, a, b, words[i + 2], 15, 718787259);
      b = ii(b, c, d, a, words[i + 9], 21, -343485551);
      a = add32(a, oa);
      b = add32(b, ob);
      c = add32(c, oc);
      d = add32(d, od);
    }

    return toHex(a) + toHex(b) + toHex(c) + toHex(d);
  }

  function b64(bytes) {
    let binary = '';
    for (let i = 0; i < bytes.length; i += 1) {
      binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
  }

  function unb64(value) {
    const clean = value.replace(/\s+/g, '').replace(/-/g, '+').replace(/_/g, '/');
    const padded = clean.padEnd(Math.ceil(clean.length / 4) * 4, '=');
    const binary = atob(padded);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i += 1) {
      bytes[i] = binary.charCodeAt(i);
    }
    return bytes;
  }

  function concatBytes(...parts) {
    const output = new Uint8Array(parts.reduce((total, part) => total + part.length, 0));
    let offset = 0;
    for (const part of parts) {
      output.set(part, offset);
      offset += part.length;
    }
    return output;
  }

  async function digest(value, name) {
    const subtle = getSubtle();
    const data = utf8Encode(value);
    try {
      return hex(new Uint8Array(await subtle.digest(name, data)));
    } catch {
      return hex(new Uint8Array(await subtle.digest({ name }, data)));
    }
  }

  async function deriveAesKey(secret, salt, iterations = appConfig().encryptionIterations) {
    if (iterations < PBKDF2_ITER_MIN || iterations > appConfig().maxEncryptionIterations) {
      throw Error('Unsupported key-derivation parameters.');
    }

    const subtle = getSubtle();
    const material = await subtle.importKey('raw', utf8Encode(secret), { name: 'PBKDF2' }, false, [
      'deriveKey',
    ]);

    return subtle.deriveKey(
      { name: 'PBKDF2', salt, iterations, hash: 'SHA-256' },
      material,
      { name: 'AES-GCM', length: 256 },
      false,
      ['encrypt', 'decrypt']
    );
  }

  async function api(params, method, signal) {
    const tool = String(params.tool || '');
    const resolved = method
      || (['hash', 'base64', 'encryption', 'hash-validation'].includes(tool) ? 'POST' : 'GET');
    const post = resolved === 'POST';
    const path = '/api/' + encodeURIComponent(tool);
    let response;
    try {
      response = await fetch(post ? path : path + '?' + new URLSearchParams(params), {
        method: post ? 'POST' : 'GET',
        headers: {
          Accept: 'application/json',
          ...(post ? { 'Content-Type': 'application/json' } : {}),
        },
        body: post ? JSON.stringify(params) : undefined,
        signal,
      });
    } catch (error) {
      if (error && error.name === 'AbortError') throw error;
      throw Error('Network error. Check your connection and try again.');
    }

    const text = await response.text();
    let data = null;
    try {
      data = JSON.parse(text);
    } catch {
      throw Error(response.status === 429 ? 'Too many requests. Try again shortly.' : 'Request failed');
    }
    if (!response.ok || data.ok === false) {
      const message = data.error && typeof data.error === 'object' && typeof data.error.message === 'string'
        ? data.error.message
        : (response.status === 429 ? 'Too many requests. Try again shortly.' : 'Request failed');
      throw Error(message);
    }
    return data;
  }

  function randomChoice(chars, count) {
    const output = new Array(count);
    const max = Math.floor(256 / chars.length) * chars.length;
    const buffer = new Uint8Array(1);

    for (let i = 0; i < count; i += 1) {
      let value;
      do {
        crypto.getRandomValues(buffer);
        value = buffer[0];
      } while (value >= max);
      output[i] = chars[value % chars.length];
    }

    return output.join('');
  }

  function uuid() {
    if (crypto.randomUUID) return crypto.randomUUID();

    const bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;

    return [...bytes]
      .map((byte, index) => byte.toString(16).padStart(2, '0') + ([3, 5, 7, 9].includes(index) ? '-' : ''))
      .join('');
  }

  function initSearch() {
    const modal = $('#searchModal');
    const search = $('#toolSearch');
    const openBtn = $('#openSearch');
    const closeBtn = $('#closeSearch');
    const items = [...document.querySelectorAll('[data-search-item]')];
    const empty = $('#toolEmpty');

    if (!modal || !search || !openBtn) return;

    const visibleItems = () => items.filter((item) => !item.hidden);

    const setActive = (index) => {
      const list = visibleItems();
      list.forEach((item, itemIndex) => {
        item.classList.toggle('bg-soft', itemIndex === index);
      });
      list[index]?.scrollIntoView({ block: 'nearest' });
    };

    const activeIndex = () => {
      const list = visibleItems();
      return Math.max(0, list.findIndex((item) => item.classList.contains('bg-soft')));
    };

    const filter = () => {
      const query = search.value.trim().toLowerCase();
      let visible = 0;
      for (const item of items) {
        const match = !query || (item.dataset.search || '').toLowerCase().includes(query);
        item.hidden = !match;
        item.classList.remove('bg-soft');
        if (match) visible += 1;
      }
      if (empty) empty.hidden = visible > 0;
      if (visible > 0) setActive(0);
    };

    const open = () => {
      if (!modal.open) modal.showModal();
      filter();
      search.focus();
      search.select();
    };

    const close = () => {
      if (modal.open) modal.close();
    };

    openBtn.addEventListener('click', open);
    closeBtn?.addEventListener('click', close);
    search.addEventListener('input', filter);
    modal.addEventListener('click', (event) => {
      if (event.target === modal) close();
    });
    modal.addEventListener('close', () => openBtn.focus());

    search.addEventListener('keydown', (event) => {
      const list = visibleItems();
      if (!list.length) return;
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        setActive(Math.min(list.length - 1, activeIndex() + 1));
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        setActive(Math.max(0, activeIndex() - 1));
      } else if (event.key === 'Enter') {
        const current = list[activeIndex()];
        if (current) {
          event.preventDefault();
          current.click();
        }
      }
    });

    document.addEventListener('keydown', (event) => {
      const commandK = event.key.toLowerCase() === 'k' && (event.metaKey || event.ctrlKey);
      const slash = event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey;
      if (!commandK && !slash) return;
      const tag = document.activeElement?.tagName;
      if (slash && (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT')) return;
      event.preventDefault();
      open();
    });
  }

  function initPassword() {
    const lengthInput = $('#len');
    const output = $('#passwordOut');

    const generate = () => {
      let chars = '';
      if ($('#upper').checked) chars += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
      if ($('#lower').checked) chars += 'abcdefghijklmnopqrstuvwxyz';
      if ($('#numbers').checked) chars += '0123456789';
      if ($('#symbols').checked) chars += '!@#$%^&*()-_=+[]{}';
      output.textContent = chars
        ? randomChoice(chars, Number(lengthInput.value))
        : 'Select at least one character set';
    };

    lengthInput.oninput = () => {
      $('#lenOut').textContent = lengthInput.value;
    };
    $('#generate').onclick = generate;
    $('#copy').onclick = () => copyText(output.textContent);
    bindSubmit(generate, ['#len', '#upper', '#lower', '#numbers', '#symbols']);
    generate();
  }

  function initHash() {
    const names = { sha256: 'SHA-256', sha384: 'SHA-384', sha512: 'SHA-512' };

    const syncCost = () => {
      $('#cost').classList.toggle('hidden', $('#algorithm').value !== 'bcrypt');
    };

    syncCost();
    $('#algorithm').onchange = syncCost;

    const run = async () => {
      const value = $('#input').value;
      const algorithm = $('#algorithm').value;
      const button = $('#run');
      setBusy(button, true, 'Hashing…');

      try {
        if (names[algorithm] && canDigest()) {
          setResult($('#output'), await digest(value, names[algorithm]));
          return;
        }

        if (algorithm === 'bcrypt') {
          const cost = Number($('#cost').value);
          const maxCost = appConfig().maxBcryptCost;
          if (!Number.isInteger(cost) || cost < 4 || cost > maxCost) {
            throw Error(`bcrypt cost must be an integer from 4 to ${maxCost}`);
          }
        }

        const response = await api(
          {
            tool: 'hash',
            str: value,
            algorithm,
            ...(algorithm === 'bcrypt' ? { cost: $('#cost').value } : {}),
          },
          'POST'
        );
        const body = response.data || {};
        setResult(
          $('#output'),
          algorithm === 'all'
            ? JSON.stringify(body.hashes || {}, null, 2)
            : body.hash || JSON.stringify(body, null, 2)
        );
      } catch (error) {
        setResult($('#output'), error.message);
      } finally {
        setBusy(button, false);
      }
    };

    $('#run').onclick = run;
    bindSubmit(run);
    $('#copy').onclick = () => copyText($('#output').textContent);
  }

  function initTimestamp() {
    const refresh = () => {
      const now = Date.now();
      $('#currentTimestamp').textContent = Math.floor(now / 1000);
      $('#currentUtc').textContent = new Date(now).toISOString();
    };

    refresh();
    setInterval(refresh, 1000);

    const run = () => {
      const value = Number($('#input').value);
      const seconds = $('#unit').value === 'ms' ? value / 1000 : value;
      if (!Number.isFinite(seconds)) {
        setResult($('#output'), 'Enter a valid timestamp');
        return;
      }

      const date = new Date(seconds * 1000);
      if (Number.isNaN(date.getTime())) {
        setResult($('#output'), 'Timestamp is outside the supported date range');
        return;
      }

      setResult($('#output'), JSON.stringify(
        {
          unix_seconds: seconds,
          unix_milliseconds: Math.round(seconds * 1000),
          iso_8601: date.toISOString(),
          utc: date.toISOString().replace('T', ' ').replace('Z', ' UTC'),
          local: date.toString(),
        },
        null,
        2
      ));
    };

    $('#run').onclick = run;
    bindSubmit(run);
    $('#copy').onclick = () => copyText($('#output').textContent);
  }

  function initJson() {
    const format = () => {
      try {
        setResult($('#output'), JSON.stringify(JSON.parse($('#input').value), null, 2));
      } catch (error) {
        setResult($('#output'), 'Invalid JSON: ' + error.message);
      }
    };

    $('#run').onclick = format;
    bindSubmit(format);
    $('#minify').onclick = () => {
      try {
        setResult($('#output'), JSON.stringify(JSON.parse($('#input').value)));
      } catch (error) {
        setResult($('#output'), 'Invalid JSON: ' + error.message);
      }
    };
    $('#copy').onclick = () => copyText($('#output').textContent);
  }

  function initUuid() {
    const run = () => {
      $('#output').textContent = uuid();
    };
    $('#run').onclick = run;
    bindSubmit(run);
    $('#copy').onclick = () => copyText($('#output').textContent);
    run();
  }

  function qrToCanvas(qr, cellSize = 8, margin = 4) {
    const count = qr.getModuleCount();
    const size = (count + margin * 2) * cellSize;
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    canvas.className = 'h-auto max-w-[min(100%,280px)] rounded-xl bg-white';
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', 'Generated QR code');
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, size, size);
    ctx.fillStyle = '#000000';
    for (let row = 0; row < count; row += 1) {
      for (let col = 0; col < count; col += 1) {
        if (qr.isDark(row, col)) {
          ctx.fillRect((col + margin) * cellSize, (row + margin) * cellSize, cellSize, cellSize);
        }
      }
    }
    return canvas;
  }

  function qrToSvg(qr, cellSize = 8, margin = 4) {
    const count = qr.getModuleCount();
    const size = (count + margin * 2) * cellSize;
    const rects = [];
    for (let row = 0; row < count; row += 1) {
      for (let col = 0; col < count; col += 1) {
        if (qr.isDark(row, col)) {
          rects.push(
            `<rect x="${(col + margin) * cellSize}" y="${(row + margin) * cellSize}" width="${cellSize}" height="${cellSize}"/>`
          );
        }
      }
    }
    return `<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#ffffff"/>${rects.join('')}</svg>`;
  }

  function wifiEscape(value) {
    return String(value).replace(/([\\;,:"])/g, '\\$1');
  }

  function vcardEscape(value) {
    return String(value).replace(/\\/g, '\\\\').replace(/\n/g, '\\n').replace(/,/g, '\\,').replace(/;/g, '\\;');
  }

  function setFieldError(id, message) {
    const field = document.getElementById(id);
    const err = document.querySelector(`[data-error-for="${id}"]`);
    if (field) {
      if (message) field.setAttribute('aria-invalid', 'true');
      else field.removeAttribute('aria-invalid');
    }
    if (err) {
      err.textContent = message || '';
      err.hidden = !message;
      err.classList.toggle('font-medium', Boolean(message));
      err.classList.toggle('text-ink', Boolean(message));
    }
  }

  function digitsOnly(value) {
    return String(value).replace(/\D/g, '');
  }

  function normalizeUpiPa(value) {
    return String(value).toLowerCase();
  }

  function qrPayload() {
    const type = $('#qrType')?.value || 'text';
    const errorIds = [
      'input',
      'qrFirstName',
      'qrMailTo',
      'qrWifiSsid',
      'qrUpiId',
      'qrAccount',
      'qrIfsc',
      'qrUpiAmount',
      'qrCcCard',
      'qrCcMobile',
      'qrCcLast4',
    ];
    for (const id of errorIds) setFieldError(id, '');

    if (type === 'text') {
      const value = $('#input')?.value || '';
      if (!value.trim()) {
        return { ok: false, payload: '', status: 'Enter text to generate' };
      }
      return { ok: true, payload: value, status: '' };
    }

    if (type === 'contact') {
      const first = ($('#qrFirstName')?.value || '').trim();
      const last = ($('#qrLastName')?.value || '').trim();
      const phone = ($('#qrPhone')?.value || '').trim();
      const email = ($('#qrContactEmail')?.value || '').trim();
      const url = ($('#qrWebsite')?.value || '').trim();
      if (!first) {
        return { ok: false, payload: '', status: 'First name is required' };
      }
      const fn = [first, last].filter(Boolean).join(' ');
      const lines = [
        'BEGIN:VCARD',
        'VERSION:3.0',
        `N:${vcardEscape(last)};${vcardEscape(first)};;;`,
        `FN:${vcardEscape(fn)}`,
      ];
      if (phone) lines.push(`TEL:${vcardEscape(phone)}`);
      if (email) lines.push(`EMAIL:${vcardEscape(email)}`);
      if (url) lines.push(`URL:${vcardEscape(url)}`);
      lines.push('END:VCARD');
      return { ok: true, payload: lines.join('\n'), status: '' };
    }

    if (type === 'email') {
      const email = ($('#qrMailTo')?.value || '').trim();
      if (!email) {
        return { ok: false, payload: '', status: 'Email is required' };
      }
      if (!email.includes('@') || email.startsWith('@') || email.endsWith('@')) {
        setFieldError('qrMailTo', 'Enter a valid email address');
        return { ok: false, payload: '', status: 'Enter a valid email address' };
      }
      const subject = ($('#qrSubject')?.value || '').trim();
      const body = ($('#qrBody')?.value || '').trim();
      let mailto = 'mailto:' + email;
      const query = [];
      if (subject) query.push('subject=' + encodeURIComponent(subject));
      if (body) query.push('body=' + encodeURIComponent(body));
      if (query.length) mailto += '?' + query.join('&');
      return { ok: true, payload: mailto, status: '' };
    }

    if (type === 'wifi') {
      const ssid = $('#qrWifiSsid')?.value || '';
      if (!ssid.trim()) {
        return { ok: false, payload: '', status: 'SSID is required' };
      }
      const security = $('#qrWifiSecurity')?.value || 'WPA';
      const password = $('#qrWifiPassword')?.value || '';
      const hidden = Boolean($('#qrWifiHidden')?.checked);
      let payload = `WIFI:T:${security};S:${wifiEscape(ssid)};`;
      if (security !== 'nopass') payload += `P:${wifiEscape(password)};`;
      if (hidden) payload += 'H:true;';
      payload += ';';
      return { ok: true, payload, status: '' };
    }

    if (type === 'upi') {
      const mode = $('#qrUpiMode')?.value || 'vpa';
      const name = ($('#qrUpiName')?.value || '').trim();
      const amount = ($('#qrUpiAmount')?.value || '').trim();
      if (amount && !/^\d+(?:\.\d{1,2})?$/.test(amount)) {
        setFieldError('qrUpiAmount', 'Amount supports up to 2 decimal places');
        return { ok: false, payload: '', status: 'Amount supports up to 2 decimal places' };
      }

      let pa = '';
      if (mode === 'account') {
        const account = ($('#qrAccount')?.value || '').trim();
        const ifsc = ($('#qrIfsc')?.value || '').trim();
        let status = '';
        if (!account) {
          status = 'Account number is required';
        }
        if (!ifsc) {
          status = status || 'IFSC is required';
        } else if (!/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/.test(ifsc)) {
          setFieldError('qrIfsc', 'Enter an 11-character IFSC');
          status = status || 'Enter an 11-character IFSC';
        }
        if (status) return { ok: false, payload: '', status };
        pa = `${account}@${ifsc}.ifsc.npci`.toLowerCase();
      } else {
        const rawUpi = ($('#qrUpiId')?.value || '').trim();
        if (!rawUpi) {
          return { ok: false, payload: '', status: 'UPI ID is required' };
        }
        const upi = rawUpi.toLowerCase();
        if (!upi.includes('@') || upi.split('@').some((part) => part === '') || /\s/.test(upi)) {
          setFieldError('qrUpiId', 'Enter a valid UPI ID');
          return { ok: false, payload: '', status: 'Enter a valid UPI ID' };
        }
        pa = upi;
      }

      const parts = [`pa=${pa}`];
      if (name) parts.push('pn=' + name.replace(/ /g, '%20'));
      if (amount) parts.push('am=' + amount);
      return { ok: true, payload: 'upi://pay?' + parts.join('&'), status: '' };
    }

    if (type === 'ccupi') {
      const bank = $('#qrCcBank')?.value || 'sbi';
      const mobileBanks = bank === 'axis' || bank === 'au';
      if (mobileBanks) {
        const mobile = digitsOnly($('#qrCcMobile')?.value || '');
        const last4 = digitsOnly($('#qrCcLast4')?.value || '');
        let status = '';
        if (mobile.length === 0) {
          status = 'Mobile number is required';
        } else if (mobile.length !== 10) {
          setFieldError('qrCcMobile', 'Enter a 10-digit mobile number');
          status = 'Enter a 10-digit mobile number';
        }
        if (last4.length === 0) {
          status = status || 'Last 4 card digits are required';
        } else if (last4.length !== 4) {
          setFieldError('qrCcLast4', 'Enter the last 4 card digits');
          status = status || 'Enter the last 4 card digits';
        }
        if (status) return { ok: false, payload: '', status };
        const pa = bank === 'axis' ? `CC.91${mobile}${last4}@axisbank` : `AUCC${mobile}${last4}@AUBANK`;
        return { ok: true, payload: `upi://pay?pa=${normalizeUpiPa(pa)}`, status: '' };
      }

      const card = digitsOnly($('#qrCcCard')?.value || '');
      const length = bank === 'amex' ? 15 : 16;
      if (card.length === 0) {
        return { ok: false, payload: '', status: 'Card number is required' };
      }
      if (card.length !== length) {
        setFieldError('qrCcCard', `Enter a ${length}-digit card number`);
        return { ok: false, payload: '', status: `Enter a ${length}-digit card number` };
      }
      const formats = {
        sbi: `Sbicard${card}@SBI`,
        icici: `ccpay${card}@icici`,
        idfc: `${card}.cc@idfcbank`,
        amex: `AEBC${card}@SC`,
      };
      return { ok: true, payload: `upi://pay?pa=${normalizeUpiPa(formats[bank] || '')}`, status: '' };
    }

    return { ok: false, payload: '', status: 'Choose a QR type' };
  }

  function initQr() {
    const frame = $('#qr');
    const pngButton = $('#qrDownloadPng');
    const svgButton = $('#qrDownloadSvg');
    const runButton = $('#run');
    const statusEl = $('#qrStatus');
    let pngUrl = '';
    let svgText = '';
    let lastPayload = '';

    const panels = {
      text: $('#qrPanelText'),
      contact: $('#qrPanelContact'),
      email: $('#qrPanelEmail'),
      wifi: $('#qrPanelWifi'),
      upi: $('#qrPanelUpi'),
      ccupi: $('#qrPanelCcupi'),
    };

    const reset = (message) => {
      pngUrl = '';
      svgText = '';
      lastPayload = '';
      if (pngButton) pngButton.disabled = true;
      if (svgButton) svgButton.disabled = true;
      frame.replaceChildren();
      frame.textContent = message;
    };

    const download = (href, name) => {
      const link = document.createElement('a');
      link.href = href;
      link.download = name;
      link.rel = 'noopener';
      link.click();
    };

    const syncPanels = () => {
      const type = $('#qrType')?.value || 'text';
      for (const [key, panel] of Object.entries(panels)) {
        if (panel) panel.hidden = key !== type;
      }
      const accountMode = $('#qrUpiMode')?.value === 'account';
      if ($('#qrUpiVpaFields')) $('#qrUpiVpaFields').hidden = accountMode;
      if ($('#qrUpiAccountFields')) $('#qrUpiAccountFields').hidden = !accountMode;
      const bank = $('#qrCcBank')?.value || 'sbi';
      const mobileBank = bank === 'axis' || bank === 'au';
      if ($('#qrCcCardFields')) $('#qrCcCardFields').hidden = mobileBank;
      if ($('#qrCcMobileFields')) $('#qrCcMobileFields').hidden = !mobileBank;
      if ($('#qrCcCard')) {
        $('#qrCcCard').placeholder = bank === 'amex' ? '15-digit card number' : '16-digit card number';
      }
    };

    const syncValidation = () => {
      const result = qrPayload();
      if (runButton) runButton.disabled = !result.ok;
      if (statusEl) statusEl.textContent = result.ok ? '' : result.status;
      return result;
    };

    const run = () => {
      const result = syncValidation();
      if (!result.ok) {
        reset(result.status || 'Enter the required fields, then generate');
        return;
      }
      if (typeof qrcode !== 'function') {
        reset('QR library failed to load. Refresh the page.');
        return;
      }

      const level = $('#qrLevel')?.value || 'M';
      frame.setAttribute('aria-busy', 'true');
      frame.classList.add('opacity-70');
      try {
        const qr = qrcode(0, level);
        qr.addData(result.payload);
        qr.make();
        const canvas = qrToCanvas(qr);
        pngUrl = canvas.toDataURL('image/png');
        svgText = qrToSvg(qr);
        lastPayload = result.payload;
        frame.replaceChildren(canvas);
        if (pngButton) pngButton.disabled = false;
        if (svgButton) svgButton.disabled = false;
      } catch (error) {
        reset(error.message || 'Could not generate a QR code for that input.');
      } finally {
        frame.removeAttribute('aria-busy');
        frame.classList.remove('opacity-70');
      }
    };

    const setChipActive = (button, active) => {
      button.setAttribute('aria-checked', active ? 'true' : 'false');
      button.tabIndex = active ? 0 : -1;
      button.classList.toggle('bg-ink', active);
      button.classList.toggle('text-white', active);
      button.classList.toggle('hover:bg-ink/90', active);
      button.classList.toggle('border', !active);
      button.classList.toggle('border-line', !active);
      button.classList.toggle('bg-white', !active);
      button.classList.toggle('text-ink', !active);
      button.classList.toggle('hover:border-leaf/50', !active);
      button.classList.toggle('hover:bg-moss/80', !active);
    };

    const bindChips = (attr, hiddenId, onSelect) => {
      const buttons = [...document.querySelectorAll(`[${attr}]`)];
      const apply = (value, announce) => {
        const hidden = document.getElementById(hiddenId);
        if (hidden) hidden.value = value;
        buttons.forEach((button) => setChipActive(button, button.getAttribute(attr) === value));
        if (announce) onSelect(value);
      };
      buttons.forEach((button) => {
        button.addEventListener('click', () => apply(button.getAttribute(attr) || '', true));
      });
      buttons[0]?.parentElement?.addEventListener('keydown', (event) => {
        const delta = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[event.key];
        if (!delta) return;
        event.preventDefault();
        const current = buttons.findIndex((button) => button.getAttribute('aria-checked') === 'true');
        const next = buttons[(Math.max(current, 0) + delta + buttons.length) % buttons.length];
        next.focus();
        apply(next.getAttribute(attr) || '', true);
      });
    };

    const onFormChange = (event) => {
      if (event.target && (event.target.id === 'qrType' || event.target.id === 'qrUpiMode' || event.target.id === 'qrCcBank')) {
        reset('Fill the required fields, then generate');
      }
      syncPanels();
      syncValidation();
    };

    bindChips('data-qr-type', 'qrType', () => {
      reset('Fill the required fields, then generate');
      syncPanels();
      syncValidation();
    });
    bindChips('data-qr-level', 'qrLevel', () => {
      if (lastPayload && typeof qrcode === 'function') run();
    });

    const root = $('[data-tool="qr"]');
    root?.addEventListener('input', onFormChange);
    root?.addEventListener('change', onFormChange);
    $('#run').onclick = run;
    bindSubmit(run, [
      '#input',
      '#qrType',
      '#qrLevel',
      '#qrFirstName',
      '#qrLastName',
      '#qrPhone',
      '#qrContactEmail',
      '#qrWebsite',
      '#qrMailTo',
      '#qrSubject',
      '#qrBody',
      '#qrWifiSsid',
      '#qrWifiPassword',
      '#qrWifiSecurity',
      '#qrUpiId',
      '#qrAccount',
      '#qrIfsc',
      '#qrUpiName',
      '#qrUpiAmount',
      '#qrCcCard',
      '#qrCcMobile',
      '#qrCcLast4',
    ]);
    pngButton?.addEventListener('click', () => {
      if (pngUrl) download(pngUrl, 'qr.png');
    });
    svgButton?.addEventListener('click', () => {
      if (!svgText) return;
      const url = URL.createObjectURL(new Blob([svgText], { type: 'image/svg+xml' }));
      download(url, 'qr.svg');
      window.setTimeout(() => URL.revokeObjectURL(url), 1500);
    });
    syncPanels();
    syncValidation();
  }

  function initRegex() {
    const TIMEOUT_MS = 400;
    const MAX_MATCHES = 200;

    const runLocal = (source, flags, value) => {
      const pattern = new RegExp(source, flags);
      if (pattern.global) {
        const matches = [];
        for (const match of value.matchAll(pattern)) {
          matches.push({ match: match[0], index: match.index });
          if (matches.length >= MAX_MATCHES) break;
        }
        return { matches, truncated: matches.length >= MAX_MATCHES };
      }
      const match = value.match(pattern);
      return { matches: match ? [{ match: match[0], index: match.index }] : [], truncated: false };
    };

    const runInWorker = (source, flags, value) =>
      new Promise((resolve, reject) => {
        let worker;
        try {
          worker = new Worker(appConfig().regexWorker);
        } catch {
          reject(Error('worker unavailable'));
          return;
        }
        const timer = window.setTimeout(() => {
          worker.terminate();
          reject(Error('Regex took too long. Simplify the pattern or shorten the test string.'));
        }, TIMEOUT_MS);
        worker.onmessage = (event) => {
          window.clearTimeout(timer);
          worker.terminate();
          const payload = event.data;
          if (payload && payload.ok) {
            resolve({ matches: payload.matches, truncated: !!payload.truncated });
            return;
          }
          reject(Error(payload && payload.error ? payload.error : 'Invalid regex'));
        };
        worker.onerror = () => {
          window.clearTimeout(timer);
          worker.terminate();
          reject(Error('Regex worker failed'));
        };
        worker.postMessage({ source, flags, value, maxMatches: MAX_MATCHES });
      });

    const run = async () => {
      try {
        const source = $('#pattern').value;
        const flags = $('#flags').value;
        if (source.length > 256) {
          throw Error('Pattern is limited to 256 characters.');
        }
        if (!/^[gimsuy]*$/.test(flags)) {
          throw Error('Flags may only include g, i, m, s, u, and y.');
        }
        const value = $('#input').value;
        if (value.length > 100000) {
          throw Error('Test string is limited to 100,000 characters.');
        }

        let result;
        try {
          result = await runInWorker(source, flags, value);
        } catch (error) {
          if (error instanceof Error && error.message === 'worker unavailable') {
            if (value.length > 10000 || source.length > 64) {
              throw Error(
                'Regex worker unavailable for this input size. Use a modern browser, or shorten the pattern/test string.'
              );
            }
            result = runLocal(source, flags, value);
          } else {
            throw error;
          }
        }

        setResult(
          $('#output'),
          JSON.stringify(
            {
              valid: true,
              matched: result.matches.length > 0,
              truncated: result.truncated,
              matches: result.matches,
            },
            null,
            2
          )
        );
      } catch (error) {
        setResult($('#output'), 'Invalid regex: ' + error.message);
      }
    };

    $('#run').onclick = run;
    bindSubmit(run);
  }

  function initBase64() {
    const output = $('#output');
    const input = $('#input');

    const encode = () => {
      try {
        setResult(output, b64(utf8Encode(input.value)));
      } catch (error) {
        setResult(output, error.message);
      }
    };

    $('#encode').onclick = encode;
    bindSubmit(encode);
    $('#decode').onclick = () => {
      try {
        setResult(output, utf8Decode(unb64(input.value.trim())));
      } catch {
        setResult(output, 'Invalid Base64');
      }
    };
    $('#copy').onclick = () => copyText(output.textContent);
  }

  function initJwt() {
    const run = () => {
      try {
        const parts = $('#input').value.trim().split('.');
        if (parts.length !== 3) {
          throw Error('A JWT must contain three dot-separated parts.');
        }
        $('#headerOut').textContent = JSON.stringify(JSON.parse(utf8Decode(unb64(parts[0]))), null, 2);
        $('#payloadOut').textContent = JSON.stringify(JSON.parse(utf8Decode(unb64(parts[1]))), null, 2);
      } catch (error) {
        $('#headerOut').textContent = '';
        $('#payloadOut').textContent = 'Invalid JWT: ' + error.message;
      }
    };

    $('#run').onclick = run;
    bindSubmit(run);
  }

  function parseUA(ua) {
    const browser = ua.includes('Edg/')
      ? 'Edge'
      : ua.includes('OPR/')
        ? 'Opera'
        : ua.includes('Firefox/')
          ? 'Firefox'
          : ua.includes('Chrome/')
            ? 'Chrome'
            : ua.includes('Safari/')
              ? 'Safari'
              : 'Unknown';
    const version = (ua.match(/(?:Edg|OPR|Firefox|Chrome|Version)\/([\d.]+)/) || [])[1] || '';
    const os = ua.includes('Windows')
      ? 'Windows'
      : ua.includes('Android')
        ? 'Android'
        : ua.includes('iPhone') || ua.includes('iPad')
          ? 'iOS'
          : ua.includes('Mac OS X')
            ? 'macOS'
            : ua.includes('Linux')
              ? 'Linux'
              : 'Unknown';
    const device = /Mobi|Android|iPhone|iPad/i.test(ua) ? 'Mobile/Tablet' : 'Desktop';

    return {
      user_agent: ua,
      browser,
      version,
      os,
      device,
      mobile: device !== 'Desktop',
      language: navigator.language,
      platform: navigator.platform,
    };
  }

  function initUserAgent() {
    const input = $('#input');

    const run = () => {
      const data = parseUA(input.value.trim() || navigator.userAgent);
      const cards = $('#uaCards');
      cards.replaceChildren();
      for (const [label, value] of [
        ['Browser', `${data.browser}${data.version ? ' ' + data.version : ''}`],
        ['Operating system', data.os],
        ['Device', data.device],
        ['Mobile', data.mobile ? 'Yes' : 'No'],
      ]) {
        const card = document.createElement('div');
        card.className = 'rounded-2xl border border-line bg-soft px-4 py-4 text-left';
        const caption = document.createElement('span');
        caption.className = 'mb-1 block text-xs font-bold uppercase tracking-wide text-muted';
        caption.textContent = label;
        const strong = document.createElement('strong');
        strong.className = 'block break-words text-base leading-snug tracking-tight text-ink sm:text-lg';
        strong.textContent = String(value);
        card.append(caption, strong);
        cards.append(card);
      }
      $('#uaOutput').textContent = JSON.stringify(data, null, 2);
    };

    input.value = navigator.userAgent;
    input.addEventListener('input', run);
    $('#copy').onclick = () => copyText($('#uaOutput').textContent);
    run();
  }

  function mdEscape(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function sanitizeHttpUrl(raw) {
    if (typeof raw !== 'string' || /[\s<>"'`\\]/.test(raw)) return '';
    try {
      const url = new URL(raw);
      if (url.protocol !== 'http:' && url.protocol !== 'https:') return '';
      return url.href;
    } catch {
      return '';
    }
  }

  function formatMarks(value) {
    return mdEscape(value)
      .replace(/`([^`]+)`/g, '<code>$1</code>')
      .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
      .replace(/__([^_]+)__/g, '<strong>$1</strong>')
      .replace(/\*([^*]+)\*/g, '<em>$1</em>')
      .replace(/(^|[\s(])_([^_\s][^_]*)_(?=[\s).,!?:;]|$)/g, '$1<em>$2</em>');
  }

  function inline(value) {
    const link = /\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g;
    let html = '';
    let last = 0;
    let match = link.exec(value);
    while (match) {
      html += formatMarks(value.slice(last, match.index));
      const href = sanitizeHttpUrl(match[2]);
      html += href
        ? `<a href="${mdEscape(href)}" target="_blank" rel="noopener noreferrer">${mdEscape(match[1])}</a>`
        : formatMarks(match[0]);
      last = match.index + match[0].length;
      match = link.exec(value);
    }
    return html + formatMarks(value.slice(last));
  }

  function markdown(source) {
    const lines = source.replace(/\r/g, '').split('\n');
    let html = '';
    let inCode = false;
    let inList = false;

    for (const line of lines) {
      if (line.startsWith('```')) {
        html += inCode ? '</code></pre>' : '<pre><code>';
        inCode = !inCode;
        continue;
      }
      if (inCode) {
        html += mdEscape(line) + '\n';
        continue;
      }
      if (/^\s*-\s+/.test(line)) {
        if (!inList) {
          html += '<ul>';
          inList = true;
        }
        html += '<li>' + inline(line.replace(/^\s*-\s+/, '')) + '</li>';
        continue;
      }
      if (inList) {
        html += '</ul>';
        inList = false;
      }
      if (!line.trim()) {
        html += '<div class="h-1.5"></div>';
        continue;
      }
      const heading = line.match(/^(#{1,6})\s+(.+)$/);
      if (heading) {
        html += `<h${heading[1].length}>${inline(heading[2])}</h${heading[1].length}>`;
        continue;
      }
      if (/^>\s?/.test(line)) {
        html += '<blockquote>' + inline(line.replace(/^>\s?/, '')) + '</blockquote>';
        continue;
      }
      html += '<p>' + inline(line) + '</p>';
    }

    if (inList) html += '</ul>';
    if (inCode) html += '</code></pre>';
    return html;
  }

  function initMarkdown() {
    const input = $('#input');
    const preview = $('#preview');
    const stats = $('#mdStats');
    const sample = [
      '# Markdown preview',
      '',
      'Write **bold**, *italic*, `code`, and [links](https://example.com).',
      '',
      '> Clean local rendering with no account required.',
      '',
      '- Fast updates',
      '- Wide editor and preview',
      '- Private by default',
      '',
      '```',
      'console.log("hello tools");',
      '```',
    ].join('\n');

    const render = () => {
      const value = input.value;
      const allowed = new Set(['H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'P', 'STRONG', 'EM', 'CODE', 'PRE', 'UL', 'LI', 'BLOCKQUOTE', 'A', 'DIV']);
      const parsed = new DOMParser().parseFromString(`<div id="md-root">${markdown(value)}</div>`, 'text/html');
      const root = parsed.getElementById('md-root');
      const copyNode = (node) => {
        if (node.nodeType === Node.TEXT_NODE) {
          return document.createTextNode(node.nodeValue || '');
        }
        if (node.nodeType !== Node.ELEMENT_NODE) {
          return document.createTextNode('');
        }
        const tag = node.tagName;
        if (!allowed.has(tag)) {
          const fragment = document.createDocumentFragment();
          for (const child of node.childNodes) fragment.append(copyNode(child));
          return fragment;
        }
        const el = document.createElement(tag.toLowerCase());
        if (tag === 'A') {
          const href = sanitizeHttpUrl(node.getAttribute('href') || '');
          if (!href) {
            const fragment = document.createDocumentFragment();
            for (const child of node.childNodes) fragment.append(copyNode(child));
            return fragment;
          }
          el.setAttribute('href', href);
          el.setAttribute('target', '_blank');
          el.setAttribute('rel', 'noopener noreferrer');
        }
        if (tag === 'DIV') {
          el.className = 'h-1.5';
        }
        for (const child of node.childNodes) el.append(copyNode(child));
        return el;
      };
      const clean = document.createElement('div');
      if (root) {
        for (const child of root.childNodes) clean.append(copyNode(child));
      }
      preview.replaceChildren(...clean.childNodes);
      stats.textContent = `${value.length} characters · ${value.length ? value.split(/\n/).length : 0} lines`;
    };

    input.addEventListener('input', render);
    $('#mdSample').onclick = () => {
      input.value = sample;
      render();
      input.focus();
    };
    $('#mdClear').onclick = () => {
      input.value = '';
      render();
      input.focus();
    };
    $('#copy').onclick = () => copyText(input.value);
    render();
  }

  function initIp() {
    const run = async () => {
      const button = $('#run');
      setBusy(button, true, 'Checking…');
      try {
        const response = await api({ tool: 'ip' });
        const data = response.data || {};
        $('#ipOutput').textContent = data.ip || 'Not detected on this connection';
        $('#ipVersion').textContent = data.version
          ? `IPv${data.version} detected · server-observed REMOTE_ADDR`
          : 'No valid REMOTE_ADDR on this connection';
        setResult($('#ipDetails'), JSON.stringify(data, null, 2));
      } catch (error) {
        $('#ipOutput').textContent = 'Error';
        $('#ipVersion').textContent = error.message;
        setResult($('#ipDetails'), error.message);
      } finally {
        setBusy(button, false);
      }
    };

    $('#run').onclick = run;
    $('#copy').onclick = () => copyText($('#ipDetails').textContent);
    run();
  }

  function initSecret() {
    const lengthInput = $('#secretLen');
    const output = $('#secretOutput');

    const generate = () => {
      const length = Number(lengthInput.value);
      const format = $('#secretFormat').value;
      const bytes = new Uint8Array(format === 'hex' ? Math.ceil(length / 2) : Math.ceil(length * 0.75));
      crypto.getRandomValues(bytes);
      let value = format === 'hex' ? hex(bytes) : b64(bytes);
      if (format === 'base64url') {
        value = value.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
      }
      output.textContent = value.slice(0, length);
    };

    lengthInput.oninput = () => {
      $('#secretLenOut').textContent = lengthInput.value;
    };
    $('#run').onclick = generate;
    bindSubmit(generate, ['#secretLen', '#secretFormat']);
    $('#copy').onclick = () => copyText(output.textContent);
    generate();
  }

  function encodePayload(payload) {
    return JSON.stringify(payload, null, 2);
  }

  function encryptionAad(version, alg, kdf, iterations, saltB64, ivB64) {
    return utf8Encode(
      [version, String(alg).toUpperCase(), String(kdf).toUpperCase(), String(iterations), saltB64, ivB64].join('|')
    );
  }

  function u32Be(value) {
    return new Uint8Array([(value >>> 24) & 255, (value >>> 16) & 255, (value >>> 8) & 255, value & 255]);
  }

  function readU32Be(bytes, offset) {
    return ((bytes[offset] << 24) | (bytes[offset + 1] << 16) | (bytes[offset + 2] << 8) | bytes[offset + 3]) >>> 0;
  }

  /**
   * Compact opaque Base64 of one payload.
   * Binary: "TJ" | version(u8) | iter(u32 BE) | salt(16) | iv(12) | tag(16) | ct
   */
  function encodeCompact(payload) {
    const version = Number(payload.v);
    const iterations = Number(payload.iter);
    const salt = unb64(String(payload.salt || ''));
    const iv = unb64(String(payload.iv || ''));
    const ct = unb64(String(payload.ct || ''));
    const tag = unb64(String(payload.tag || ''));
    if (
      ![1, 2].includes(version) ||
      !Number.isInteger(iterations) ||
      iterations < PBKDF2_ITER_MIN ||
      iterations > appConfig().maxEncryptionIterations ||
      salt.length !== 16 ||
      iv.length !== 12 ||
      tag.length !== 16
    ) {
      throw Error('Unable to encode compact encrypted payload.');
    }

    const header = concatBytes(
      utf8Encode('TJ'),
      new Uint8Array([version]),
      u32Be(iterations),
      salt,
      iv,
      tag
    );
    return b64(concatBytes(header, ct));
  }

  function decodeCompact(binary) {
    if (binary.length < 51) return null;
    if (binary[0] !== 0x54 || binary[1] !== 0x4a) return null;
    const version = binary[2];
    if (![1, 2].includes(version)) return null;
    const iterations = readU32Be(binary, 3);
    const salt = binary.slice(7, 23);
    const iv = binary.slice(23, 35);
    const tag = binary.slice(35, 51);
    const ct = binary.slice(51);
    if (
      iterations < PBKDF2_ITER_MIN ||
      iterations > appConfig().maxEncryptionIterations ||
      salt.length !== 16 ||
      iv.length !== 12 ||
      tag.length !== 16
    ) {
      return null;
    }
    return {
      v: version,
      alg: 'AES-256-GCM',
      kdf: 'PBKDF2-SHA256',
      iter: iterations,
      salt: b64(salt),
      iv: b64(iv),
      ct: b64(ct),
      tag: b64(tag),
    };
  }

  async function encryptLocal(value, key, version = 2) {
    if (version !== 1 && version !== 2) {
      throw Error('Unsupported encryption version.');
    }

    const subtle = getSubtle();
    const salt = crypto.getRandomValues(new Uint8Array(16));
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const iterations = appConfig().encryptionIterations;
    const saltB64 = b64(salt);
    const ivB64 = b64(iv);
    const derived = await deriveAesKey(key, salt, iterations);
    const params = { name: 'AES-GCM', iv, tagLength: 128 };
    if (version === 2) {
      params.additionalData = encryptionAad(2, 'AES-256-GCM', 'PBKDF2-SHA256', iterations, saltB64, ivB64);
    }
    const raw = new Uint8Array(await subtle.encrypt(params, derived, utf8Encode(value)));
    const tag = raw.slice(-16);
    const ct = raw.slice(0, -16);

    const payload = {
      v: version,
      alg: 'AES-256-GCM',
      kdf: 'PBKDF2-SHA256',
      iter: iterations,
      salt: saltB64,
      iv: ivB64,
      ct: b64(ct),
      tag: b64(tag),
    };

    return {
      payload,
      json: encodePayload(payload),
      compact: encodeCompact(payload),
    };
  }

  async function decryptVersioned(payload, key) {
    const subtle = getSubtle();
    const version = Number(payload.v);
    const alg = String(payload.alg || '').toUpperCase();
    const kdf = String(payload.kdf || '').toUpperCase();
    if (![1, 2].includes(version) || alg !== 'AES-256-GCM' || kdf !== 'PBKDF2-SHA256') {
      throw Error('Unsupported encrypted payload.');
    }

    const iterations = Number(payload.iter);
    const saltB64 = String(payload.salt || '');
    const ivB64 = String(payload.iv || '');
    const salt = unb64(saltB64);
    const iv = unb64(ivB64);
    const ct = unb64(String(payload.ct || ''));
    const tag = unb64(String(payload.tag || ''));
    if (salt.length < 16 || iv.length !== 12 || tag.length !== 16) {
      throw Error('Invalid encrypted payload.');
    }

    const derived = await deriveAesKey(key, salt, iterations);
    const params = { name: 'AES-GCM', iv, tagLength: 128 };
    if (version === 2) {
      params.additionalData = encryptionAad(version, alg, kdf, iterations, saltB64, ivB64);
    }
    const plain = await subtle.decrypt(params, derived, concatBytes(ct, tag));
    return utf8Decode(new Uint8Array(plain));
  }

  async function decryptLegacy(value, key) {
    const subtle = getSubtle();
    const raw = unb64(value.trim());
    if (raw.length < 44) throw Error('Invalid encrypted value.');
    const salt = raw.slice(0, 16);
    const iv = raw.slice(16, 28);
    const tag = raw.slice(28, 44);
    const cipher = raw.slice(44);
    const derived = await deriveAesKey(key, salt, LEGACY_PBKDF2_ITERATIONS);
    const plain = await subtle.decrypt({ name: 'AES-GCM', iv }, derived, concatBytes(cipher, tag));
    return utf8Decode(new Uint8Array(plain));
  }

  async function decryptLocal(value, key) {
    const trimmed = value.trim();
    try {
      const parsed = JSON.parse(trimmed);
      if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
        return decryptVersioned(parsed, key);
      }
    } catch {
      // Fall through to compact / legacy binary formats.
    }

    try {
      const raw = unb64(trimmed);
      const compact = decodeCompact(raw);
      if (compact) {
        return decryptVersioned(compact, key);
      }
    } catch {
      // Fall through to the previous binary payload format.
    }

    return decryptLegacy(trimmed, key);
  }

  function initEncryption() {
    const modeSelect = $('#mode');
    const input = $('#input');
    const inputLabel = $('#inputLabel');
    const runBtn = $('#run');
    const hint = $('#encHint');
    const encryptOutputs = $('#encryptOutputs');
    const decryptOutputs = $('#decryptOutputs');
    const encError = $('#encError');

    const hideResults = () => {
      if (encryptOutputs) encryptOutputs.hidden = true;
      if (decryptOutputs) decryptOutputs.hidden = true;
      if (encError) encError.hidden = true;
      setResult($('#compactOutput'), '');
      setResult($('#jsonOutput'), '');
      setResult($('#decryptOutput'), '');
      setResult($('#errorOutput'), '');
    };

    const showError = (message) => {
      hideResults();
      if (encError) encError.hidden = false;
      setResult($('#errorOutput'), message);
    };

    const currentMode = () => (modeSelect?.value === 'decrypt' ? 'decrypt' : 'encrypt');

    const applyMode = () => {
      const mode = currentMode();
      const decrypting = mode === 'decrypt';
      if (inputLabel) {
        inputLabel.textContent = decrypting ? 'Encrypted Input' : 'String / Input';
      }
      if (input) {
        input.placeholder = decrypting
          ? 'Paste compact Base64 or encrypted JSON'
          : 'Hello world';
      }
      if (runBtn) {
        runBtn.textContent = decrypting ? 'Decrypt' : 'Encrypt';
        runBtn.dataset.label = runBtn.textContent;
      }
      if (hint) {
        hint.textContent = decrypting
          ? 'Paste encrypted compact or JSON input, choose Decrypt, then run. Format is auto-detected. Ctrl+Enter to run.'
          : 'Enter text and a secret, choose Encrypt, then run. One Encrypt yields compact and JSON. Ctrl+Enter to run.';
      }
      hideResults();
    };

    if (modeSelect) {
      modeSelect.value = 'encrypt';
      modeSelect.addEventListener('change', applyMode);
    }
    applyMode();

    const run = async () => {
      const button = $('#run');
      const mode = currentMode();
      setBusy(button, true, 'Working…');
      try {
        const key = $('#key').value;
        const value = $('#input').value;
        if (!key) throw Error('Secret key is required.');
        if (!canAesGcm()) {
          throw Error(
            'This page needs a modern browser and HTTPS (or localhost) so Web Crypto can run locally. Plaintext and the secret key are not sent to the server.'
          );
        }

        if (mode === 'decrypt') {
          const plain = await decryptLocal(value, key);
          hideResults();
          if (decryptOutputs) decryptOutputs.hidden = false;
          setResult($('#decryptOutput'), plain);
        } else {
          const result = await encryptLocal(value, key, 2);
          hideResults();
          if (encryptOutputs) encryptOutputs.hidden = false;
          setResult($('#compactOutput'), result.compact);
          setResult($('#jsonOutput'), result.json);
        }
      } catch (error) {
        const message = String(error.message || error);
        const failed = /operation-specific|OperationError|decrypt/i.test(message)
          ? 'Decryption failed. Check the secret key and encrypted value.'
          : message;
        showError('Encryption/decryption failed: ' + failed);
      } finally {
        setBusy(button, false);
      }
    };

    $('#run').onclick = run;
    bindSubmit(run);
    $('#copyCompact')?.addEventListener('click', () => copyText($('#compactOutput')?.textContent, $('#copyCompact')));
    $('#copyJson')?.addEventListener('click', () => copyText($('#jsonOutput')?.textContent, $('#copyJson')));
    $('#copyDecrypt')?.addEventListener('click', () => copyText($('#decryptOutput')?.textContent, $('#copyDecrypt')));
  }

  function looksLikeBcrypt(hash) {
    return /^\$2[abxy]\$\d{2}\$[A-Za-z0-9./]{53}$/.test(String(hash).trim());
  }

  function detectHashAlgorithm(hash) {
    const trimmed = String(hash).trim();
    if (looksLikeBcrypt(trimmed)) return 'bcrypt';
    const hexValue = trimmed.replace(/^0x/i, '');
    if (!/^[0-9a-fA-F]+$/.test(hexValue)) return null;
    switch (hexValue.length) {
      case 32:
        return 'md5';
      case 40:
        return 'sha1';
      case 64:
        return 'sha256';
      case 96:
        return 'sha384';
      case 128:
        return 'sha512';
      default:
        return null;
    }
  }

  function normalizeHexHash(hash) {
    return String(hash).trim().replace(/^0x/i, '').toLowerCase();
  }

  async function localDigest(value, algorithm) {
    const names = { sha256: 'SHA-256', sha384: 'SHA-384', sha512: 'SHA-512', sha1: 'SHA-1' };
    if (algorithm === 'md5') return md5Hex(value);
    if (!names[algorithm]) throw Error('Unsupported algorithm');
    if (!canDigest()) {
      throw Error('This page needs a modern browser so Web Crypto can run locally.');
    }
    return digest(value, names[algorithm]);
  }

  function initHashValidation() {
    const labels = {
      auto: 'Auto',
      sha256: 'SHA-256',
      sha384: 'SHA-384',
      sha512: 'SHA-512',
      sha1: 'SHA-1',
      md5: 'MD5',
      bcrypt: 'bcrypt',
    };

    const run = async () => {
      const value = $('#input').value;
      const hashValue = ($('#hashValue')?.value || '').trim();
      const algorithm = $('#algorithm').value;
      const button = $('#run');
      setBusy(button, true, 'Checking…');
      try {
        if (!hashValue) throw Error('Hash is required.');

        const detected = algorithm === 'auto' ? detectHashAlgorithm(hashValue) : algorithm;
        const useServer = algorithm === 'bcrypt' || detected === 'bcrypt';
        if (useServer) {
          const response = await api(
            {
              tool: 'hash-validation',
              str: value,
              hash: hashValue,
              algorithm,
            },
            'POST'
          );
          const body = response.data || {};
          const label = labels[body.algorithm] || body.algorithm || 'bcrypt';
          setResult(
            $('#output'),
            `${body.match ? 'Match' : 'Does Not Match'}\nAlgorithm: ${label}${body.auto ? ' (auto)' : ''}`
          );
          return;
        }

        const candidates = detected ? [detected] : ['sha256', 'sha384', 'sha512', 'sha1', 'md5'];
        const expected = normalizeHexHash(hashValue);
        let matched = null;
        for (const candidate of candidates) {
          const actual = await localDigest(value, candidate);
          if (actual === expected) {
            matched = candidate;
            break;
          }
        }
        const shown = matched || detected || algorithm;
        setResult(
          $('#output'),
          `${matched ? 'Match' : 'Does Not Match'}\nAlgorithm: ${labels[shown] || shown}${algorithm === 'auto' ? ' (auto)' : ''}`
        );
      } catch (error) {
        setResult($('#output'), error.message);
      } finally {
        setBusy(button, false);
      }
    };

    $('#run').onclick = run;
    bindSubmit(run, ['#input', '#hashValue', '#algorithm']);
    $('#copy').onclick = () => copyText($('#output').textContent);
  }

  function clampCron(value, min, max, fallback) {
    const n = Number(value);
    if (!Number.isInteger(n) || n < min || n > max) return fallback;
    return n;
  }

  function cronToken(value) {
    const token = String(value).trim() || '*';
    return /^[0-9*,\-\/]+$/.test(token) ? token : '*';
  }

  function pad2(value) {
    return String(value).padStart(2, '0');
  }

  function weekdayName(value) {
    return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][Number(value)] || 'Sunday';
  }

  function describeCron(minute, hour, dom, month, dow) {
    if (minute === '*' && hour === '*' && dom === '*' && month === '*' && dow === '*') {
      return 'Every minute';
    }
    if (hour === '*' && dom === '*' && month === '*' && dow === '*' && minute !== '*') {
      return `At minute ${minute} of every hour`;
    }
    if (dom === '*' && month === '*' && dow === '*' && minute !== '*' && hour !== '*') {
      return `At ${pad2(hour)}:${pad2(minute)} every day`;
    }
    if (dom === '*' && month === '*' && minute !== '*' && hour !== '*' && dow !== '*') {
      return `At ${pad2(hour)}:${pad2(minute)} on ${weekdayName(dow)}`;
    }
    if (month === '*' && dow === '*' && minute !== '*' && hour !== '*' && dom !== '*') {
      return `At ${pad2(hour)}:${pad2(minute)} on day ${dom} of every month`;
    }
    return `At minute ${minute}, hour ${hour}, day ${dom}, month ${month}, weekday ${dow}`;
  }

  function initCron() {
    const panels = {
      hourly: $('#cronHourly'),
      daily: $('#cronDaily'),
      weekly: $('#cronWeekly'),
      monthly: $('#cronMonthly'),
      custom: $('#cronCustom'),
    };

    const render = () => {
      const mode = $('#cronMode')?.value || 'minute';
      for (const [key, panel] of Object.entries(panels)) {
        if (panel) panel.hidden = key !== mode;
      }

      let minute = '*';
      let hour = '*';
      let dom = '*';
      let month = '*';
      let dow = '*';

      if (mode === 'hourly') {
        minute = String(clampCron($('#cronHourlyMinute')?.value, 0, 59, 0));
      } else if (mode === 'daily') {
        hour = String(clampCron($('#cronDailyHour')?.value, 0, 23, 0));
        minute = String(clampCron($('#cronDailyMinute')?.value, 0, 59, 0));
      } else if (mode === 'weekly') {
        dow = String(clampCron($('#cronWeeklyDay')?.value, 0, 6, 0));
        hour = String(clampCron($('#cronWeeklyHour')?.value, 0, 23, 0));
        minute = String(clampCron($('#cronWeeklyMinute')?.value, 0, 59, 0));
      } else if (mode === 'monthly') {
        dom = String(clampCron($('#cronMonthlyDay')?.value, 1, 31, 1));
        hour = String(clampCron($('#cronMonthlyHour')?.value, 0, 23, 0));
        minute = String(clampCron($('#cronMonthlyMinute')?.value, 0, 59, 0));
      } else if (mode === 'custom') {
        minute = cronToken($('#cronCustomMinute')?.value);
        hour = cronToken($('#cronCustomHour')?.value);
        dom = cronToken($('#cronCustomDom')?.value);
        month = cronToken($('#cronCustomMonth')?.value);
        dow = cronToken($('#cronCustomDow')?.value);
      }

      const expression = `${minute} ${hour} ${dom} ${month} ${dow}`;
      if ($('#cronOutput')) $('#cronOutput').textContent = expression;
      if ($('#cronHuman')) $('#cronHuman').textContent = describeCron(minute, hour, dom, month, dow);
    };

    const root = $('[data-tool="cron"]');
    root?.addEventListener('input', render);
    root?.addEventListener('change', render);
    $('#copy').onclick = () => copyText($('#cronOutput')?.textContent);
    render();
  }

  function sshString(bytesOrString) {
    const bytes = typeof bytesOrString === 'string' ? utf8Encode(bytesOrString) : bytesOrString;
    const out = new Uint8Array(4 + bytes.length);
    out[0] = (bytes.length >>> 24) & 255;
    out[1] = (bytes.length >>> 16) & 255;
    out[2] = (bytes.length >>> 8) & 255;
    out[3] = bytes.length & 255;
    out.set(bytes, 4);
    return out;
  }

  function sshMpint(bytes) {
    let value = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
    let i = 0;
    while (i < value.length - 1 && value[i] === 0) i += 1;
    value = value.slice(i);
    if (value.length === 0) value = new Uint8Array([0]);
    if (value[0] & 0x80) {
      const padded = new Uint8Array(value.length + 1);
      padded.set(value, 1);
      value = padded;
    }
    return sshString(value);
  }

  function wrapPem(label, bytes) {
    const encoded = b64(bytes);
    const lines = encoded.match(/.{1,70}/g) || [encoded];
    return `-----BEGIN ${label}-----\n${lines.join('\n')}\n-----END ${label}-----\n`;
  }

  function opensshPrivateKey(publicBlob, privateBody, comment) {
    const check = crypto.getRandomValues(new Uint8Array(4));
    let inner = concatBytes(check, check, privateBody, sshString(comment));
    const padLen = (8 - (inner.length % 8)) % 8;
    if (padLen) {
      const pad = new Uint8Array(padLen);
      for (let i = 0; i < padLen; i += 1) pad[i] = i + 1;
      inner = concatBytes(inner, pad);
    }
    const payload = concatBytes(
      utf8Encode('openssh-key-v1\0'),
      sshString('none'),
      sshString('none'),
      sshString(new Uint8Array(0)),
      new Uint8Array([0, 0, 0, 1]),
      sshString(publicBlob),
      sshString(inner)
    );
    return wrapPem('OPENSSH PRIVATE KEY', payload);
  }

  function jwkBytes(jwk, field) {
    return unb64(String(jwk[field] || ''));
  }

  async function generateSshEd25519(comment) {
    const pair = await getSubtle().generateKey({ name: 'Ed25519' }, true, ['sign', 'verify']);
    const pkcs8 = new Uint8Array(await getSubtle().exportKey('pkcs8', pair.privateKey));
    let pub;
    try {
      pub = new Uint8Array(await getSubtle().exportKey('raw', pair.publicKey));
    } catch {
      const spki = new Uint8Array(await getSubtle().exportKey('spki', pair.publicKey));
      pub = spki.slice(-32);
    }
    if (pkcs8.length < 32 || pub.length !== 32) {
      throw Error('Unexpected Ed25519 key encoding.');
    }
    const seed = pkcs8.slice(-32);
    const secret = concatBytes(seed, pub);
    const publicBlob = concatBytes(sshString('ssh-ed25519'), sshString(pub));
    const privateBody = concatBytes(sshString('ssh-ed25519'), sshString(pub), sshString(secret));
    return {
      algorithm: 'ed25519',
      comment,
      public_key: `ssh-ed25519 ${b64(publicBlob)}${comment ? ' ' + comment : ''}`,
      private_key: opensshPrivateKey(publicBlob, privateBody, comment),
    };
  }

  async function generateSshRsa(bits, comment) {
    const pair = await getSubtle().generateKey(
      {
        name: 'RSASSA-PKCS1-v1_5',
        modulusLength: bits,
        publicExponent: new Uint8Array([1, 0, 1]),
        hash: 'SHA-256',
      },
      true,
      ['sign', 'verify']
    );
    const jwk = await getSubtle().exportKey('jwk', pair.privateKey);
    if (!jwk?.n || !jwk?.e || !jwk?.d || !jwk?.p || !jwk?.q || !jwk?.qi) {
      throw Error('This browser did not export a complete RSA key.');
    }
    const n = jwkBytes(jwk, 'n');
    const e = jwkBytes(jwk, 'e');
    const d = jwkBytes(jwk, 'd');
    const p = jwkBytes(jwk, 'p');
    const q = jwkBytes(jwk, 'q');
    const qi = jwkBytes(jwk, 'qi');
    const publicBlob = concatBytes(sshString('ssh-rsa'), sshMpint(e), sshMpint(n));
    const privateBody = concatBytes(
      sshString('ssh-rsa'),
      sshMpint(n),
      sshMpint(e),
      sshMpint(d),
      sshMpint(qi),
      sshMpint(p),
      sshMpint(q)
    );
    return {
      algorithm: 'rsa' + bits,
      comment,
      public_key: `ssh-rsa ${b64(publicBlob)}${comment ? ' ' + comment : ''}`,
      private_key: opensshPrivateKey(publicBlob, privateBody, comment),
    };
  }

  function downloadText(name, value) {
    const url = URL.createObjectURL(new Blob([value], { type: 'text/plain' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = name;
    link.rel = 'noopener';
    link.click();
    window.setTimeout(() => URL.revokeObjectURL(url), 1500);
  }

  function initSsh() {
    const outputs = $('#sshOutputs');
    const errorBox = $('#sshError');

    const showError = (message) => {
      if (outputs) outputs.hidden = true;
      if (errorBox) errorBox.hidden = false;
      setResult($('#output'), message);
    };

    const run = async () => {
      const button = $('#run');
      const algorithm = $('#sshAlgorithm')?.value || 'ed25519';
      const comment = ($('#sshComment')?.value || '').replace(/[\r\n]/g, '').slice(0, 100);
      const passphrase = $('#sshPassphrase')?.value || '';
      setBusy(button, true, 'Generating…');
      try {
        let result;
        try {
          if (passphrase) {
            throw Error('Passphrase-protected keys use the API.');
          }
          if (algorithm === 'ed25519') {
            result = await generateSshEd25519(comment);
          } else if (algorithm === 'rsa2048' || algorithm === 'rsa4096') {
            if (!getSubtle()?.generateKey) {
              throw Error('Web Crypto is unavailable.');
            }
            result = await generateSshRsa(algorithm === 'rsa4096' ? 4096 : 2048, comment);
          } else {
            throw Error('Unsupported algorithm.');
          }
        } catch (error) {
          const payload = { tool: 'ssh', algorithm, comment };
          if (passphrase) payload.passphrase = passphrase;
          const response = await api(payload, passphrase ? 'POST' : 'GET');
          result = response.data || {};
          if (!result.public_key || !result.private_key) {
            throw error;
          }
        }

        if (errorBox) errorBox.hidden = true;
        if (outputs) outputs.hidden = false;
        setResult($('#sshPublic'), result.public_key || '');
        setResult($('#sshPrivate'), result.private_key || '');
        $('#downloadPublic').onclick = () => {
          downloadText(algorithm === 'ed25519' ? 'id_ed25519.pub' : 'id_rsa.pub', result.public_key || '');
        };
        $('#downloadPrivate').onclick = () => {
          downloadText(algorithm === 'ed25519' ? 'id_ed25519' : 'id_rsa', result.private_key || '');
        };
      } catch (error) {
        showError(error.message || 'Unable to generate an SSH key.');
      } finally {
        setBusy(button, false);
      }
    };

    $('#run').onclick = run;
    bindSubmit(run, ['#sshAlgorithm', '#sshComment', '#sshPassphrase']);
    $('#copyPublic')?.addEventListener('click', () => copyText($('#sshPublic')?.textContent, $('#copyPublic')));
    $('#copyPrivate')?.addEventListener('click', () => copyText($('#sshPrivate')?.textContent, $('#copyPrivate')));
    $('#sshPassphraseToggle')?.addEventListener('click', () => {
      const field = $('#sshPassphrase');
      const toggle = $('#sshPassphraseToggle');
      if (!field || !toggle) return;
      const hidden = field.type === 'password';
      field.type = hidden ? 'text' : 'password';
      toggle.textContent = hidden ? 'Hide' : 'Show';
      toggle.setAttribute('aria-pressed', hidden ? 'true' : 'false');
    });
  }

  function dnsTypeName(type) {
    return ({ 1: 'A', 2: 'NS', 5: 'CNAME', 15: 'MX', 16: 'TXT', 28: 'AAAA' })[Number(type)] || String(type || '');
  }

  function dnsStatusName(status) {
    return ({ 0: 'NOERROR', 1: 'FORMERR', 2: 'SERVFAIL', 3: 'NXDOMAIN', 4: 'NOTIMP', 5: 'REFUSED' })[Number(status)] || ('STATUS_' + status);
  }

  const DNS_TYPES = { A: 1, NS: 2, CNAME: 5, MX: 15, TXT: 16, AAAA: 28 };
  const DNS_ENDPOINTS = {
    default: { url: 'https://dns.rajujha.dev/dns-query/tools', mode: 'rfc8484' },
    cloudflare: { url: 'https://cloudflare-dns.com/dns-query', mode: 'json' },
  };
  const DNS_DOH_TIMEOUT_MS = 2500;
  const dnsCache = new Map();
  const dnsInflight = new Map();
  const dnsCorsBlocked = new Set();

  function validDnsHost(host) {
    const value = String(host || '').trim();
    if (!value || value.length > 253 || value.includes('://') || /\s/.test(value)) return false;
    const normalized = value.replace(/\.$/, '');
    if (!normalized) return false;
    if (/^(\d{1,3}\.){3}\d{1,3}$/.test(normalized) || normalized.includes(':')) return true;
    return /^(?=.{1,253}$)(?:[a-zA-Z0-9_](?:[a-zA-Z0-9_-]{0,61}[a-zA-Z0-9_])?\.)*[a-zA-Z0-9_](?:[a-zA-Z0-9_-]{0,61}[a-zA-Z0-9_])?$/.test(normalized);
  }

  function dnsErrorMessage(error) {
    const code = error && error.dnsCode;
    if (code === 'invalid-host') return 'Enter a valid hostname.';
    if (code === 'cors') return 'The DNS provider blocked this browser request.';
    if (code === 'network') return 'The DNS lookup was blocked on this network.';
    if (code === 'unavailable') return 'The DNS provider is unavailable. Try again or switch provider.';
    return 'The DNS lookup failed. Try again or switch provider.';
  }

  function dnsFailure(code) {
    const error = Error(code);
    error.dnsCode = code;
    return error;
  }

  function dnsBase64Url(bytes) {
    let binary = '';
    bytes.forEach((byte) => {
      binary += String.fromCharCode(byte);
    });
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
  }

  function encodeDnsQuery(name, type) {
    const qtype = DNS_TYPES[type] || 1;
    const id = crypto.getRandomValues(new Uint8Array(2));
    const labels = String(name).replace(/\.$/, '').split('.').filter(Boolean);
    const parts = [id[0], id[1], 1, 0, 0, 1, 0, 0, 0, 0, 0, 0];
    for (const label of labels) {
      const bytes = utf8Encode(label);
      if (bytes.length < 1 || bytes.length > 63) throw dnsFailure('invalid-host');
      parts.push(bytes.length, ...bytes);
    }
    parts.push(0, (qtype >> 8) & 255, qtype & 255, 0, 1);
    return Uint8Array.from(parts);
  }

  function readDnsName(view, offset, depth = 0) {
    if (depth > 10) throw dnsFailure('unavailable');
    const labels = [];
    let jumped = false;
    let returnOffset = offset;
    while (offset < view.byteLength) {
      const len = view.getUint8(offset);
      if (len === 0) {
        offset = jumped ? returnOffset : offset + 1;
        break;
      }
      if ((len & 0xc0) === 0xc0) {
        if (offset + 1 >= view.byteLength) throw dnsFailure('unavailable');
        const pointer = ((len & 0x3f) << 8) | view.getUint8(offset + 1);
        if (!jumped) {
          returnOffset = offset + 2;
          jumped = true;
        }
        offset = pointer;
        depth += 1;
        if (depth > 10) throw dnsFailure('unavailable');
        continue;
      }
      offset += 1;
      let label = '';
      for (let i = 0; i < len && offset < view.byteLength; i += 1) {
        label += String.fromCharCode(view.getUint8(offset));
        offset += 1;
      }
      labels.push(label);
    }
    return { name: labels.join('.'), offset };
  }

  function readDnsRdata(view, offset, type, rdlength) {
    if (type === 1 && rdlength === 4) {
      return [view.getUint8(offset), view.getUint8(offset + 1), view.getUint8(offset + 2), view.getUint8(offset + 3)].join('.');
    }
    if (type === 28 && rdlength === 16) {
      const parts = [];
      for (let i = 0; i < 8; i += 1) parts.push(view.getUint16(offset + i * 2).toString(16));
      return parts.join(':');
    }
    if (type === 15 && rdlength >= 3) {
      const preference = view.getUint16(offset);
      const exchange = readDnsName(view, offset + 2).name;
      return preference + ' ' + exchange;
    }
    if (type === 16) {
      let out = '';
      let i = 0;
      while (i < rdlength) {
        const size = view.getUint8(offset + i);
        i += 1;
        for (let j = 0; j < size && i < rdlength; j += 1, i += 1) {
          out += String.fromCharCode(view.getUint8(offset + i));
        }
      }
      return out;
    }
    if (type === 2 || type === 5) {
      return readDnsName(view, offset).name;
    }
    return '';
  }

  function parseDnsMessage(buffer) {
    const view = new DataView(buffer);
    if (view.byteLength < 12) throw dnsFailure('unavailable');
    const status = view.getUint16(2) & 0x0f;
    const qdcount = view.getUint16(4);
    const ancount = view.getUint16(6);
    let offset = 12;
    for (let i = 0; i < qdcount; i += 1) {
      offset = readDnsName(view, offset).offset + 4;
    }
    const answers = [];
    for (let i = 0; i < ancount && answers.length < 8; i += 1) {
      const name = readDnsName(view, offset);
      offset = name.offset;
      if (offset + 10 > view.byteLength) break;
      const type = view.getUint16(offset);
      const ttl = view.getUint32(offset + 4);
      const rdlength = view.getUint16(offset + 8);
      offset += 10;
      const data = readDnsRdata(view, offset, type, rdlength);
      offset += rdlength;
      answers.push({
        name: name.name,
        type: dnsTypeName(type),
        ttl,
        data,
      });
    }
    return { status, answers };
  }

  function normalizeDoh(decoded, host, type, provider) {
    const answers = [];
    const raw = Array.isArray(decoded.Answer) ? decoded.Answer : [];
    for (const item of raw) {
      if (!item || typeof item !== 'object') continue;
      answers.push({
        name: String(item.name || ''),
        type: dnsTypeName(item.type) || type,
        ttl: Number(item.TTL || 0),
        data: String(item.data || ''),
      });
      if (answers.length >= 8) break;
    }
    const status = Number(decoded.Status || 0);
    return {
      provider,
      host: String(host || '').replace(/\.$/, ''),
      type,
      status,
      status_name: dnsStatusName(status),
      answers,
    };
  }

  function classifyDnsFetchError(error, parentSignal) {
    if (error && error.dnsCode) return error;
    if (error && error.name === 'AbortError') {
      if (parentSignal && parentSignal.aborted) return error;
      return dnsFailure('unavailable');
    }
    if (typeof navigator !== 'undefined' && navigator.onLine === false) return dnsFailure('network');
    return dnsFailure('cors');
  }

  function withDnsTimeout(parentSignal, ms) {
    const controller = new AbortController();
    const onParentAbort = () => controller.abort();
    if (parentSignal) {
      if (parentSignal.aborted) {
        controller.abort();
      } else {
        parentSignal.addEventListener('abort', onParentAbort, { once: true });
      }
    }
    const timer = window.setTimeout(() => controller.abort(), ms);
    return {
      signal: controller.signal,
      cleanup() {
        window.clearTimeout(timer);
        parentSignal?.removeEventListener('abort', onParentAbort);
      },
    };
  }

  function publicDnsResult(data, fallbackHost, fallbackType) {
    const answers = [];
    const raw = Array.isArray(data && data.answers) ? data.answers.slice(0, 8) : [];
    for (const item of raw) {
      if (!item || typeof item !== 'object') continue;
      answers.push({
        name: String(item.name || ''),
        type: String(item.type || fallbackType || ''),
        ttl: Number(item.ttl || 0),
        data: String(item.data || ''),
      });
      if (answers.length >= 8) break;
    }
    const status = Number((data && data.status) || 0);
    return {
      provider: String((data && data.provider) || ''),
      host: String((data && data.host) || fallbackHost || '').replace(/\.$/, ''),
      type: String((data && data.type) || fallbackType || ''),
      status,
      status_name: String((data && data.status_name) || dnsStatusName(status)),
      answers,
    };
  }

  async function dohFetch(url, accept, parentSignal) {
    const timed = withDnsTimeout(parentSignal, DNS_DOH_TIMEOUT_MS);
    try {
      return await fetch(url, {
        method: 'GET',
        headers: { Accept: accept },
        mode: 'cors',
        credentials: 'omit',
        cache: 'no-store',
        redirect: 'error',
        signal: timed.signal,
      });
    } catch (error) {
      throw classifyDnsFetchError(error, parentSignal);
    } finally {
      timed.cleanup();
    }
  }

  async function fetchDoh(provider, host, type, signal) {
    const spec = DNS_ENDPOINTS[provider];
    if (!spec) throw dnsFailure('unavailable');
    const normalizedHost = host.replace(/\.$/, '');
    if (spec.mode === 'json') {
      const url = spec.url + '?' + new URLSearchParams({ name: normalizedHost, type });
      const response = await dohFetch(url, 'application/dns-json', signal);
      if (!response.ok) throw dnsFailure('unavailable');
      let decoded;
      try {
        decoded = await response.json();
      } catch {
        throw dnsFailure('unavailable');
      }
      if (!decoded || typeof decoded !== 'object') throw dnsFailure('unavailable');
      return publicDnsResult(normalizeDoh(decoded, normalizedHost, type, provider), normalizedHost, type);
    }

    const query = encodeDnsQuery(normalizedHost, type);
    const url = spec.url + '?dns=' + dnsBase64Url(query);
    const response = await dohFetch(url, 'application/dns-message', signal);
    if (!response.ok) throw dnsFailure('unavailable');
    const buffer = await response.arrayBuffer();
    let parsed;
    try {
      parsed = parseDnsMessage(buffer);
    } catch (error) {
      throw classifyDnsFetchError(error, signal);
    }
    return publicDnsResult({
      provider,
      host: normalizedHost,
      type,
      status: parsed.status,
      status_name: dnsStatusName(parsed.status),
      answers: parsed.answers,
    }, normalizedHost, type);
  }

  async function lookupDnsBrowserFirst(host, type, provider, signal) {
    const key = provider + '|' + host + '|' + type;
    if (dnsCache.has(key)) return dnsCache.get(key);
    if (dnsInflight.has(key)) return dnsInflight.get(key);

    const order = provider === 'cloudflare' ? ['cloudflare', 'default'] : ['default', 'cloudflare'];
    const pending = (async () => {
      let lastError = dnsFailure('unavailable');
      for (const candidate of order) {
        if (dnsCorsBlocked.has(candidate)) continue;
        try {
          const data = await fetchDoh(candidate, host, type, signal);
          dnsCache.set(key, data);
          dnsCache.set(candidate + '|' + host + '|' + type, data);
          return data;
        } catch (error) {
          if (error && error.name === 'AbortError' && signal && signal.aborted) throw error;
          const classified = classifyDnsFetchError(error, signal);
          if (classified && classified.name === 'AbortError') throw classified;
          if (classified.dnsCode === 'cors') dnsCorsBlocked.add(candidate);
          lastError = classified;
        }
      }
      try {
        const response = await api({ tool: 'dns', host, type, provider }, 'GET', signal);
        const data = publicDnsResult(response.data || {}, host, type);
        dnsCache.set(key, data);
        return data;
      } catch (error) {
        if (error && error.name === 'AbortError') throw error;
        throw lastError.dnsCode ? lastError : dnsFailure('unavailable');
      }
    })();

    dnsInflight.set(key, pending);
    try {
      return await pending;
    } finally {
      dnsInflight.delete(key);
    }
  }

  function initDns() {
    const cards = $('#dnsCards');
    let controller = null;

    const showFriendlyError = (message) => {
      cards.replaceChildren();
      const empty = document.createElement('div');
      empty.className = 'w-full min-w-0 overflow-hidden rounded-2xl border border-line bg-soft px-4 py-4 text-left';
      const caption = document.createElement('span');
      caption.className = 'mb-1 block text-xs font-bold uppercase tracking-wide text-muted';
      caption.textContent = 'Lookup failed';
      const strong = document.createElement('strong');
      strong.className = 'block min-w-0 break-all whitespace-pre-wrap text-base leading-snug tracking-tight text-ink sm:text-lg';
      strong.textContent = message;
      empty.append(caption, strong);
      cards.append(empty);
      setResult($('#output'), message);
    };

    const run = async () => {
      const button = $('#run');
      const host = ($('#dnsHost')?.value || '').trim();
      const type = $('#dnsType')?.value || 'A';
      const provider = $('#dnsProvider')?.value || 'default';
      controller?.abort();
      controller = new AbortController();
      const signal = controller.signal;
      setBusy(button, true, 'Looking up…');
      try {
        if (!host) throw dnsFailure('invalid-host');
        if (!validDnsHost(host)) throw dnsFailure('invalid-host');
        const data = await lookupDnsBrowserFirst(host, type, provider, signal);
        const display = publicDnsResult(data, host, type);
        const answers = display.answers;
        cards.replaceChildren();
        if (answers.length === 0) {
          const empty = document.createElement('div');
          empty.className = 'w-full min-w-0 overflow-hidden rounded-2xl border border-line bg-soft px-4 py-4 text-left';
          const caption = document.createElement('span');
          caption.className = 'mb-1 block text-xs font-bold uppercase tracking-wide text-muted';
          caption.textContent = display.status_name || 'No answers';
          const strong = document.createElement('strong');
          strong.className = 'block min-w-0 break-all whitespace-pre-wrap text-base leading-snug tracking-tight text-ink sm:text-lg';
          strong.textContent = 'No records returned for this query.';
          empty.append(caption, strong);
          cards.append(empty);
        } else {
          for (const answer of answers) {
            const card = document.createElement('div');
            card.className = 'w-full min-w-0 overflow-hidden rounded-2xl border border-line bg-soft px-4 py-4 text-left';
            const caption = document.createElement('span');
            caption.className = 'mb-1 block text-xs font-bold uppercase tracking-wide text-muted';
            caption.textContent = `${answer.type || type} · TTL ${answer.ttl ?? 0}`;
            const strong = document.createElement('strong');
            strong.className = 'block min-w-0 break-all whitespace-pre-wrap text-base leading-snug tracking-tight text-ink sm:text-lg';
            strong.textContent = String(answer.data || '');
            const name = document.createElement('small');
            name.className = 'mt-1 block break-all text-xs text-muted';
            name.textContent = String(answer.name || host);
            card.append(caption, strong, name);
            cards.append(card);
          }
        }
        setResult($('#output'), JSON.stringify(display, null, 2));
      } catch (error) {
        if (error && error.name === 'AbortError') return;
        showFriendlyError(dnsErrorMessage(error));
      } finally {
        if (!signal.aborted) setBusy(button, false);
      }
    };

    $('#run').onclick = run;
    bindSubmit(run, ['#dnsHost', '#dnsType', '#dnsProvider']);
    $('#copy').onclick = () => copyText($('#output').textContent, $('#copy'));
  }

  function initApiExampleCopy() {
    document.querySelectorAll('[data-copy-target]').forEach((button) => {
      button.addEventListener('click', () => {
        const target = document.querySelector(button.getAttribute('data-copy-target') || '');
        copyText(target?.textContent || '', button);
      });
    });
  }

  const boot = {
    password: initPassword,
    hash: initHash,
    timestamp: initTimestamp,
    json: initJson,
    uuid: initUuid,
    qr: initQr,
    regex: initRegex,
    base64: initBase64,
    jwt: initJwt,
    'user-agent': initUserAgent,
    markdown: initMarkdown,
    ip: initIp,
    secret: initSecret,
    encryption: initEncryption,
    'hash-validation': initHashValidation,
    cron: initCron,
    ssh: initSsh,
    dns: initDns,
  };

  try {
    initSearch();
    initApiExampleCopy();
    boot[tool]?.();
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Failed to initialize this tool.';
    showToast(message);
    const fallback = $('#output') || $('#errorOutput') || $('#passwordOut') || $('#secretOutput');
    if (fallback) {
      setResult(fallback, message);
    }
  }
})();
