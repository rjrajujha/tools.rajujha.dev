(() => {
  const $ = (selector) => document.querySelector(selector);
  const tool = $('[data-tool]')?.dataset.tool;
  const toastEl = $('#toast');
  let toastTimer = 0;

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
    const original = button.dataset.label || button.textContent;
    button.dataset.label = original;
    button.textContent = 'Copied';
    window.setTimeout(() => {
      button.textContent = original;
    }, 900);
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

  async function deriveAesKey(secret, salt) {
    const subtle = getSubtle();
    const material = await subtle.importKey('raw', utf8Encode(secret), { name: 'PBKDF2' }, false, [
      'deriveKey',
    ]);

    return subtle.deriveKey(
      { name: 'PBKDF2', salt, iterations: 120000, hash: 'SHA-256' },
      material,
      { name: 'AES-GCM', length: 256 },
      false,
      ['encrypt', 'decrypt']
    );
  }

  async function api(params, method = 'GET') {
    const post = method === 'POST';
    const response = await fetch(post ? '/api.php' : '/api.php?' + new URLSearchParams(params), {
      method: post ? 'POST' : 'GET',
      headers: {
        Accept: 'application/json',
        ...(post ? { 'Content-Type': 'application/json' } : {}),
      },
      body: post ? JSON.stringify(params) : undefined,
    });
    const data = await response.json();
    if (!response.ok || data.ok === false) {
      throw Error(data.error || 'Request failed');
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
          $('#output').textContent = await digest(value, names[algorithm]);
          return;
        }

        const data = await api(
          {
            tool: 'hash',
            str: value,
            algorithm,
            ...(algorithm === 'bcrypt' ? { cost: $('#cost').value } : {}),
          },
          'POST'
        );
        $('#output').textContent =
          algorithm === 'all' ? JSON.stringify(data, null, 2) : data.hash || JSON.stringify(data, null, 2);
      } catch (error) {
        $('#output').textContent = error.message;
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
        $('#output').textContent = 'Enter a valid timestamp';
        return;
      }

      const date = new Date(seconds * 1000);
      if (Number.isNaN(date.getTime())) {
        $('#output').textContent = 'Timestamp is outside the supported date range';
        return;
      }

      $('#output').textContent = JSON.stringify(
        {
          unix_seconds: seconds,
          unix_milliseconds: Math.round(seconds * 1000),
          iso_8601: date.toISOString(),
          utc: date.toISOString().replace('T', ' ').replace('Z', ' UTC'),
          local: date.toString(),
        },
        null,
        2
      );
    };

    $('#run').onclick = run;
    bindSubmit(run);
    $('#copy').onclick = () => copyText($('#output').textContent);
  }

  function initJson() {
    const format = () => {
      try {
        $('#output').textContent = JSON.stringify(JSON.parse($('#input').value), null, 2);
      } catch (error) {
        $('#output').textContent = 'Invalid JSON: ' + error.message;
      }
    };

    $('#run').onclick = format;
    bindSubmit(format);
    $('#minify').onclick = () => {
      try {
        $('#output').textContent = JSON.stringify(JSON.parse($('#input').value));
      } catch (error) {
        $('#output').textContent = 'Invalid JSON: ' + error.message;
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

  function initQr() {
    const run = () => {
      const value = $('#input').value.trim();
      const frame = $('#qr');
      if (!value) {
        frame.textContent = 'Enter text or a URL';
        return;
      }

      frame.textContent = 'Generating…';
      const image = document.createElement('img');
      image.src = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(value);
      image.alt = 'QR code for the entered text';
      image.width = 220;
      image.height = 220;
      image.className = 'h-auto max-w-[min(100%,220px)] rounded-xl';
      image.onload = () => frame.replaceChildren(image);
      image.onerror = () => {
        frame.textContent = 'Could not generate a QR code. Try again.';
      };
    };

    $('#run').onclick = run;
    bindSubmit(run);
  }

  function initRegex() {
    const run = () => {
      try {
        const pattern = new RegExp($('#pattern').value, $('#flags').value);
        const value = $('#input').value;
        const matches = pattern.global
          ? [...value.matchAll(pattern)].map((match) => ({ match: match[0], index: match.index }))
          : (() => {
              const match = value.match(pattern);
              return match ? [{ match: match[0], index: match.index }] : [];
            })();

        $('#output').textContent = JSON.stringify(
          { valid: true, matched: matches.length > 0, matches },
          null,
          2
        );
      } catch (error) {
        $('#output').textContent = 'Invalid regex: ' + error.message;
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
        output.textContent = b64(utf8Encode(input.value));
      } catch (error) {
        output.textContent = error.message;
      }
    };

    $('#encode').onclick = encode;
    bindSubmit(encode);
    $('#decode').onclick = () => {
      try {
        output.textContent = utf8Decode(unb64(input.value.trim()));
      } catch {
        output.textContent = 'Invalid Base64';
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
      $('#uaCards').innerHTML = [
        ['Browser', `${data.browser}${data.version ? ' ' + data.version : ''}`],
        ['Operating system', data.os],
        ['Device', data.device],
        ['Mobile', data.mobile ? 'Yes' : 'No'],
      ]
        .map(([label, value]) => {
          const safe = String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
          return `<div class="rounded-2xl border border-line bg-soft px-4 py-4 text-left"><span class="mb-1 block text-xs font-bold uppercase tracking-wide text-muted">${label}</span><strong class="block break-words text-base leading-snug tracking-tight text-ink sm:text-lg">${safe}</strong></div>`;
        })
        .join('');
      $('#uaOutput').textContent = JSON.stringify(data, null, 2);
    };

    input.value = navigator.userAgent;
    input.addEventListener('input', run);
    $('#copy').onclick = () => copyText($('#uaOutput').textContent);
    run();
  }

  function mdEscape(value) {
    return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function inline(value) {
    return mdEscape(value)
      .replace(/`([^`]+)`/g, '<code>$1</code>')
      .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
      .replace(/__([^_]+)__/g, '<strong>$1</strong>')
      .replace(/\*([^*]+)\*/g, '<em>$1</em>')
      .replace(/(^|[\s(])_([^_\s][^_]*)_(?=[\s).,!?:;]|$)/g, '$1<em>$2</em>')
      .replace(
        /\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g,
        '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>'
      );
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
      preview.innerHTML = markdown(value);
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

      output.textContent = 'Unavailable';
      output.classList.remove('text-ink');
      output.classList.add('text-muted');
      note.textContent = 'Not observed on this connection';
      card.classList.remove('border-[#dbe8ce]', 'bg-moss');
      card.classList.add('border-line', 'bg-soft');
    };

    const run = async () => {
      const button = $('#run');
      setBusy(button, true, 'Checking…');
      try {
        const data = await api({ tool: 'ip' });
        $('#ipOutput').textContent = data.ip || 'Unavailable';
        $('#ipVersion').textContent = data.version
          ? `IPv${data.version} · server-observed REMOTE_ADDR`
          : 'Server-observed REMOTE_ADDR';
        setFamily(4, data.ipv4);
        setFamily(6, data.ipv6);
        $('#ipDetails').textContent = JSON.stringify(data, null, 2);
      } catch (error) {
        $('#ipOutput').textContent = 'Error';
        $('#ipVersion').textContent = error.message;
        setFamily(4, null);
        setFamily(6, null);
        $('#ipDetails').textContent = error.message;
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

  async function encryptLocal(value, key) {
    const subtle = getSubtle();
    const salt = crypto.getRandomValues(new Uint8Array(16));
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const derived = await deriveAesKey(key, salt);
    const raw = new Uint8Array(await subtle.encrypt({ name: 'AES-GCM', iv }, derived, utf8Encode(value)));
    return b64(concatBytes(salt, iv, raw.slice(-16), raw.slice(0, -16)));
  }

  async function decryptLocal(value, key) {
    const subtle = getSubtle();
    const raw = unb64(value.trim());
    if (raw.length < 44) throw Error('Invalid encrypted value.');
    const salt = raw.slice(0, 16);
    const iv = raw.slice(16, 28);
    const tag = raw.slice(28, 44);
    const cipher = raw.slice(44);
    const derived = await deriveAesKey(key, salt);
    const plain = await subtle.decrypt({ name: 'AES-GCM', iv }, derived, concatBytes(cipher, tag));
    return utf8Decode(new Uint8Array(plain));
  }

  function initEncryption() {
    const run = async () => {
      const button = $('#run');
      setBusy(button, true, 'Working…');
      try {
        const mode = $('#mode').value;
        const key = $('#key').value;
        const value = $('#input').value;
        if (!key) throw Error('Secret key is required.');

        if (canAesGcm()) {
          $('#output').textContent =
            mode === 'encrypt' ? await encryptLocal(value, key) : await decryptLocal(value, key);
          return;
        }

        const data = await api({ tool: 'encryption', str: value, key, mode }, 'POST');
        $('#output').textContent = data.output;
      } catch (error) {
        $('#output').textContent = 'Encryption/decryption failed: ' + error.message;
      } finally {
        setBusy(button, false);
      }
    };

    $('#run').onclick = run;
    bindSubmit(run);
    $('#copy').onclick = () => copyText($('#output').textContent);
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

  initSearch();
  boot[tool]?.();
})();
