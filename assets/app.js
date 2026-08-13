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

    if (button.dataset.copyMode === 'icon') {
      button.classList.add('border-leaf/50', 'bg-moss', 'text-ink');
      window.setTimeout(() => {
        button.classList.remove('border-leaf/50', 'bg-moss', 'text-ink');
      }, 900);
      return;
    }

    const original = button.dataset.label || button.textContent;
    button.dataset.label = original;
    button.textContent = 'Copied';
    button.classList.add('border-leaf/50', 'bg-moss', 'text-ink');
    window.setTimeout(() => {
      button.textContent = original;
      button.classList.remove('border-leaf/50', 'bg-moss', 'text-ink');
    }, 900);
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

  async function api(params, method = 'GET') {
    const post = method === 'POST';
    let response;
    try {
      response = await fetch(post ? '/api.php' : '/api.php?' + new URLSearchParams(params), {
        method: post ? 'POST' : 'GET',
        headers: {
          Accept: 'application/json',
          ...(post ? { 'Content-Type': 'application/json' } : {}),
        },
        body: post ? JSON.stringify(params) : undefined,
      });
    } catch {
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
      throw Error(data.error || (response.status === 429 ? 'Too many requests. Try again shortly.' : 'Request failed'));
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

  function initQr() {
    const frame = $('#qr');
    const pngButton = $('#qrDownloadPng');
    const svgButton = $('#qrDownloadSvg');
    let pngUrl = '';
    let svgText = '';

    const reset = (message) => {
      pngUrl = '';
      svgText = '';
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

    const run = () => {
      const value = $('#input').value;
      if (!value.trim()) {
        reset('Enter text or a URL');
        return;
      }
      if (typeof qrcode !== 'function') {
        reset('QR library failed to load. Refresh the page.');
        return;
      }

      const level = $('#qrLevel')?.value || 'M';
      try {
        const qr = qrcode(0, level);
        qr.addData(value);
        qr.make();
        const canvas = qrToCanvas(qr);
        pngUrl = canvas.toDataURL('image/png');
        svgText = qrToSvg(qr);
        frame.replaceChildren(canvas);
        if (pngButton) pngButton.disabled = false;
        if (svgButton) svgButton.disabled = false;
      } catch (error) {
        reset(error.message || 'Could not generate a QR code for that input.');
      }
    };

    $('#run').onclick = run;
    bindSubmit(run, ['#input', '#qrLevel']);
    pngButton?.addEventListener('click', () => {
      if (pngUrl) download(pngUrl, 'qr.png');
    });
    svgButton?.addEventListener('click', () => {
      if (!svgText) return;
      const url = URL.createObjectURL(new Blob([svgText], { type: 'image/svg+xml' }));
      download(url, 'qr.svg');
      window.setTimeout(() => URL.revokeObjectURL(url), 1500);
    });
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
    const setFamily = (version, value) => {
      const output = $(`#ipv${version}Output`);
      const note = $(`#ipv${version}Note`);
      const card = output.closest('.ip-card');

      if (value) {
        output.textContent = value;
        output.classList.remove('text-muted');
        output.classList.add('text-ink');
        note.textContent = 'Observed on this connection';
        card.classList.remove('border-line', 'bg-soft');
        card.classList.add('border-[#dbe8ce]', 'bg-moss');
        return;
      }

      output.textContent = 'Not detected on this connection';
      output.classList.remove('text-ink');
      output.classList.add('text-muted');
      note.textContent = 'This connection did not expose that address family';
      card.classList.remove('border-[#dbe8ce]', 'bg-moss');
      card.classList.add('border-line', 'bg-soft');
    };

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
        setFamily(4, data.ipv4);
        setFamily(6, data.ipv6);
        setResult($('#ipDetails'), JSON.stringify(data, null, 2));
      } catch (error) {
        $('#ipOutput').textContent = 'Error';
        $('#ipVersion').textContent = error.message;
        setFamily(4, null);
        setFamily(6, null);
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
