# tools.rajujha.dev

Fast, private developer utilities. No accounts, no analytics, no database, no tracking.

Version **1.0.0**. Live site: [tools.rajujha.dev](https://tools.rajujha.dev)

PHP 8.1+, HTML, compiled Tailwind CSS, and a small JavaScript surface. Most tools run in the browser. Optional JSON APIs exist for scripts. There is no framework and no application-side storage of user input.

## Features

- Clean URLs for every utility
- Browser-first processing whenever it is safe
- JSON APIs for scripting (GET only for safe/generated values)
- No cookies, accounts, localStorage, sessionStorage, or analytics
- Security headers, CSP, and blocked access to sensitive files
- Application rate limiting on expensive APIs (20 requests / 60 seconds by default)
- `/health` for uptime checks

## Tools

| Tool | Route | Where it runs | API |
|---|---|---|---|
| Password Generator | `/password` | Browser + optional API | `GET /api/password` |
| Hash | `/hash` | Browser for SHA-2; API for MD5, SHA-1, bcrypt, all | `POST /api/hash` |
| Timestamp | `/timestamp` | Browser clock + optional API | `GET /api/timestamp` |
| JSON Decoder | `/json` | Browser only | — |
| UUID Generator | `/uuid` | Browser Web Crypto + optional API | `GET /api/uuid` |
| QR Code Generator | `/qr` | Browser only, local library | — |
| Regex Tester | `/regex` | Browser Web Worker | — |
| Base64 | `/base64` | Browser + optional API | `POST /api/base64` |
| JWT Decoder | `/jwt` | Browser only | — |
| User-Agent Parser | `/user-agent` | Browser + optional API | `GET /api/user-agent` |
| Markdown Preview | `/markdown` | Browser only | — |
| IP Checker | `/ip` | Server-observed `REMOTE_ADDR` | `GET /api/ip` |
| Secret Generator | `/secret` | Browser Web Crypto + optional API | `GET /api/secret` |
| Encrypt-Decrypt | `/encryption` | Browser Web Crypto only in the UI | `POST /api/encryption` |

## Privacy and security

- Output is escaped in PHP. Markdown allows only safe `http(s)` links
- CSP is same-origin; no third-party scripts or analytics
- Sensitive APIs (`hash`, `base64`, `encryption`) require POST — do not put secrets or plaintext in query strings
- Encrypt-Decrypt runs locally in the browser when Web Crypto is available; the UI does not silently fall back to the API
- bcrypt and encryption iteration ceilings come from `config.json`
- Application rate limiting protects expensive endpoints; identity uses `REMOTE_ADDR` (hashed on disk). Proxy headers are not trusted by default

Strength of encryption depends on secret entropy and correct use. This is not a claim that any scheme is unbreakable.

## Local development

```bash
git clone https://github.com/rjrajujha/tools.rajujha.dev.git
cd tools.rajujha.dev
npm install
npm run build
php -S 127.0.0.1:8080 router.php
```

Open [http://127.0.0.1:8080](http://127.0.0.1:8080). Use `npm run watch:css` while editing styles.

PHP’s built-in server must use `router.php`. Do not expose `router.php` as a public Apache endpoint.

## API usage

Responses use a shared envelope plus tool-specific fields:

```json
{
  "ok": true,
  "tool": "hash",
  "data": {},
  "error": null
}
```

Sensitive endpoints: `POST /api/hash`, `POST /api/base64`, `POST /api/encryption` (JSON or form-encoded). Request bodies and string inputs are limited to 65,536 bytes.

Safe GET examples: `/api/password`, `/api/uuid`, `/api/secret`, `/api/timestamp`, `/api/ip`, `/api/user-agent`.

`GET /health` returns UTC status JSON and is not cacheable.

### Encryption

```text
POST /api/encryption
Content-Type: application/json

{"str":"hello","key":"your-secret","mode":"encrypt"}
```

**Encrypt**

- `mode` must be `encrypt` or `decrypt`
- Optional `v` selects the encryption format for encrypt:
  - omit `v` → **v = 2** (default, recommended)
  - `v = 2` → current format (AES-256-GCM, PBKDF2-HMAC-SHA-256, AAD-bound metadata)
  - `v = 1` → legacy JSON format without AAD (API compatibility only)
  - any other `v` → rejected
- V2 encrypt returns both `compact` (opaque Base64) and `json`/`payload` (structured object) from **one** encryption. `output` remains the pretty-printed JSON string for compatibility. Base64 is encoding, not encryption.
- Algorithm and KDF are not client-selectable. Iteration defaults/ceilings come from `config.json` (`encryption_iterations` / `max_encryption_iterations`, currently 310000)

**Decrypt**

- Auto-detects compact Base64, V2 JSON, V1 JSON, and the older binary blob
- No `decrypt-v1` / `decrypt-v2` mode selection

The UI only offers Encrypt and Decrypt. It always encrypts with V2 and never exposes V1 as a mode. Generate secrets at `/secret`.

Example V2 payload:

```json
{
  "v": 2,
  "alg": "AES-256-GCM",
  "kdf": "PBKDF2-SHA256",
  "iter": 310000,
  "salt": "<base64>",
  "iv": "<base64>",
  "ct": "<base64>",
  "tag": "<base64>"
}
```

## Configuration

`config.json` holds author/version metadata, security ceilings, and rate-limit policy. It is not web-accessible. Do not put credentials in it.

```json
{
  "author": "Raju Jha",
  "version": "1.0.0",
  "security": {
    "bcrypt_cost": 12,
    "max_bcrypt_cost": 14,
    "encryption_iterations": 310000,
    "max_encryption_iterations": 310000
  },
  "rate_limit": {
    "enabled": true,
    "requests": 20,
    "window_seconds": 60
  },
  "client_ip": {
    "trust_cloudflare": false
  }
}
```

Invalid configuration fails safely. Missing `rate_limit.enabled` keeps limiting **on**. Set `"enabled": false` only to opt out explicitly. Absolute ceilings prevent absurd values.

## Deployment

Runtime: PHP 8.1+ with `mod_rewrite` and OpenSSL. Keep `APP_DEBUG` unset. Serve over HTTPS (Web Crypto needs a secure context).

Upload `index.php`, `api.php`, `bootstrap.php`, `config.json`, compiled assets (including `regex-worker.js` and vendored QR files), `.htaccess`, and static site files. Do not upload `node_modules/`, `tests/`, or `.github/`. Allow the process user to create `var/rate-limit/` (0700). Enable OPcache when available.

## Testing

```bash
php -l bootstrap.php
php -l index.php
php -l api.php
php -l router.php
node --check assets/app.js
npm run build
php tests/run.php
php -S 127.0.0.1:8080 router.php
php tests/http.php
```

## License

MIT. See [LICENSE](LICENSE).
