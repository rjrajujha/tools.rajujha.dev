(() => {
  const STORAGE_KEY = 'theme';
  const LIGHT = 'light';
  const DARK = 'dark';
  const root = document.documentElement;

  const systemTheme = () =>
    window.matchMedia('(prefers-color-scheme: dark)').matches ? DARK : LIGHT;

  const storedTheme = () => {
    try {
      const value = localStorage.getItem(STORAGE_KEY);
      return value === LIGHT || value === DARK ? value : null;
    } catch {
      return null;
    }
  };

  const resolvedTheme = () => storedTheme() || systemTheme();

  const applyChrome = (theme) => {
    const metaTheme = document.querySelector('meta[name="theme-color"]');
    if (metaTheme) {
      metaTheme.setAttribute('content', theme === DARK ? '#121714' : '#f3f6f0');
    }
    const button = document.getElementById('themeToggle');
    if (!button) return;
    const next = theme === DARK ? LIGHT : DARK;
    button.setAttribute('aria-pressed', theme === DARK ? 'true' : 'false');
    button.setAttribute('aria-label', next === DARK ? 'Switch to dark theme' : 'Switch to light theme');
  };

  const stored = storedTheme();
  if (stored) {
    root.setAttribute('data-theme', stored);
  }
  applyChrome(resolvedTheme());

  const media = window.matchMedia('(prefers-color-scheme: dark)');
  const onSystemChange = () => {
    if (storedTheme()) return;
    applyChrome(systemTheme());
  };
  if (typeof media.addEventListener === 'function') {
    media.addEventListener('change', onSystemChange);
  } else if (typeof media.addListener === 'function') {
    media.addListener(onSystemChange);
  }

  const bindToggle = () => {
    const button = document.getElementById('themeToggle');
    if (!button || button.dataset.bound === '1') return;
    button.dataset.bound = '1';
    applyChrome(resolvedTheme());
    button.addEventListener('click', () => {
      const next = resolvedTheme() === DARK ? LIGHT : DARK;
      try {
        localStorage.setItem(STORAGE_KEY, next);
      } catch {
        // Private mode still gets a session-only theme.
      }
      root.setAttribute('data-theme', next);
      applyChrome(next);
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindToggle);
  } else {
    bindToggle();
  }
})();
