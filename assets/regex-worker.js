/* Isolated regex execution. Do not import or eval untrusted code. */
self.onmessage = (event) => {
  const data = event.data || {};
  const source = data.source;
  const flags = data.flags;
  const value = data.value;
  const maxMatches = Number.isInteger(data.maxMatches) ? data.maxMatches : 200;

  try {
    if (typeof source !== 'string' || typeof flags !== 'string' || typeof value !== 'string') {
      throw new Error('Invalid regex request.');
    }
    if (source.length > 256) {
      throw new Error('Pattern is limited to 256 characters.');
    }
    if (!/^[gimsuy]*$/.test(flags)) {
      throw new Error('Flags may only include g, i, m, s, u, and y.');
    }
    if (value.length > 100000) {
      throw new Error('Test string is limited to 100,000 characters.');
    }
    if (maxMatches < 1 || maxMatches > 1000) {
      throw new Error('Match limit is invalid.');
    }

    const pattern = new RegExp(source, flags);
    const matches = [];

    if (pattern.global) {
      const iterator = value.matchAll(pattern);
      for (const match of iterator) {
        matches.push({ match: match[0], index: match.index });
        if (matches.length >= maxMatches) {
          break;
        }
      }
    } else {
      const match = value.match(pattern);
      if (match) {
        matches.push({ match: match[0], index: match.index });
      }
    }

    self.postMessage({ ok: true, matches, truncated: matches.length >= maxMatches });
  } catch (error) {
    self.postMessage({
      ok: false,
      error: error instanceof Error ? error.message : 'Invalid regex',
    });
  }
};
