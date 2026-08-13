# tools.rajujha.dev

Fast, private developer utilities. No accounts, no analytics, no database, no tracking.

Version **1.0.0**. Live site: [tools.rajujha.dev](https://tools.rajujha.dev)

The app is intentionally small: PHP 8.1+, HTML, compiled CSS, and a single JavaScript file. Most tools run in the browser. Optional JSON APIs exist for scripts. There is no framework and no application-side storage of user input.

## Overview

- Clean URLs for every utility
- Browser-first processing whenever it is safe
- JSON APIs for scripting, with GET only for safe/read-only work
- No cookies, accounts, localStorage, sessionStorage, or analytics
- Security headers, CSP, and blocked access to sensitive files
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
| Regex Tester | `/regex` | Browser only | — |
| Base64 | `/base64` | Browser + optional API | `POST /api/base64` |
| JWT Decoder | `/jwt` | Browser only | — |
| User-Agent Parser | `/user-agent` | Browser + optional API | `GET /api/user-agent` |
| Markdown Preview | `/markdown` | Browser only | — |
| IP Checker | `/ip` | Server-observed `REMOTE_ADDR` | `GET /api/ip` |
| Secret Generator | `/secret` | Browser Web Crypto + optional API | `GET /api/secret` |
| Encrypt - Decrypt | `/encryption` | Browser Web Crypto only in the UI | `POST /api/encryption` |

## Architecture

```
index.php          HTML routes and tool pages
api.php            JSON API
bootstrap.php      Shared helpers, errors, config, /health
router.php         Local PHP built-in server only
config.json        Author, version, and security limits
assets/app.js      Client logic
assets/app.css     Compiled Tailwind
assets/vendor/     Vendored QR library
src/input.css      Tailwind source
.htaccess          Production rewrite rules and security headers
```

`bootstrap.php` and `router.php` are not public endpoints. `config.json` is not web-accessible.

## Browser vs API processing

Interactive pages prefer local processing:

- Password, UUID, Secret: Web Crypto / `crypto.getRandomValues`
- Hash: Web Crypto for SHA-256/384/512; MD5, SHA-1, bcrypt, and `all` use `POST /api/hash`
- Encrypt - Decrypt: Web Crypto AES-256-GCM in the browser. The UI never sends plaintext or the secret key to the server. If Web Crypto is unavailable (insecure HTTP, very old browser), the page shows an error instead of falling back to the API. Generate a secret at `/secret`.
- Timestamp clock, JSON, Regex, JWT, User-Agent, Markdown, Base64 UI, QR: browser-local
- IP: the only tool that must ask the server, because the observed address is a server property
- Optional JSON APIs do not store data

QR codes are drawn onto a canvas in the browser. Content is not sent to a third-party service.

## API usage

All API routes return JSON and use HTTP status codes for invalid requests.

Success:

```json
{
  "ok": true,
  "tool": "hash",
  "data": {},
  "error": null
}
```

Error:

```json
{
  "ok": false,
  "tool": "hash",
  "data": null,
  "error": "A useful error message"
}
```

Top-level result fields such as `hash`, `output`, and `password` are still included for compatibility with existing scripts. Prefer `data` for new integrations.

`POST` bodies may be JSON (`Content-Type: application/json`) or form-encoded. Sensitive endpoints ignore query-string values for `str` and `key`.

Request bodies and string inputs are limited to 65,536 bytes.

### GET vs POST

Use **GET** only for safe or generated values:

- `GET /api/password`
- `GET /api/timestamp`
- `GET /api/uuid`
- `GET /api/secret`
- `GET /api/ip`
- `GET /api/user-agent`

Use **POST** for plaintext, passwords, keys, or anything that should not appear in URLs or access logs:

- `POST /api/hash`
- `POST /api/base64`
- `POST /api/encryption`

GET to those endpoints returns HTTP 405.

### Password

```text
GET /api/password?length=24&upper=1&lower=1&numbers=1&symbols=1&count=1
```

Cryptographically random passwords via PHP `random_int`. `length` is 8–128, `count` is 1–20. Boolean flags accept `1`/`0` or `true`/`false`. The UI generates locally; the API is optional and stores nothing.

### Hash

```text
POST /api/hash
Content-Type: application/json

{"str":"admin123","algorithm":"sha256"}
```

```text
POST /api/hash
Content-Type: application/json

{"str":"admin123","algorithm":"bcrypt","cost":12}
```

Algorithms: `md5`, `sha1`, `sha256`, `sha384`, `sha512`, `bcrypt`, `all`. bcrypt uses a fresh random salt.

- `bcrypt_cost` (default, from `config.json`): 12
- `max_bcrypt_cost` (API security ceiling, from `config.json`): 14

Omit `cost` to use `bcrypt_cost`. The API rejects any cost above `max_bcrypt_cost`. Minimum cost is 4. Do not send secrets using GET query parameters.

### Timestamp

```text
GET /api/timestamp
GET /api/timestamp?timestamp=1755000000&unit=s
GET /api/timestamp?timestamp=1755000000000&unit=ms
```

Response includes Unix seconds, milliseconds, ISO 8601 UTC and a readable UTC value. The UI clock updates locally.

### UUID

```text
GET /api/uuid?count=1
```

UUID v4. `count` is 1–100. The UI uses Web Crypto.

### Secret

```text
GET /api/secret?length=48&format=hex&count=1
```

`length` is 16–256. `format` is `hex`, `base64`, or `base64url`. The UI uses Web Crypto.

### Base64

```text
POST /api/base64
Content-Type: application/json

{"str":"hello","mode":"encode"}
```

### User-Agent

```text
GET /api/user-agent
GET /api/user-agent?ua=Mozilla/5.0%20...
```

### IP

```text
GET /api/ip
```

Reports server-observed `REMOTE_ADDR` as IPv4 or IPv6. A single TCP connection is only one address family, so the other family is `null` with status `not_detected`. This is expected. The tool does not invent addresses and does not call a third-party IP API.

`X-Forwarded-For` and `X-Real-IP` are returned under `proxy_headers` for inspection. They are not trusted. This app does not currently have a trusted-proxy configuration.

### Encryption

```text
POST /api/encryption
Content-Type: application/json

{"str":"hello","key":"your-secret","mode":"encrypt"}
```

The UI does not use this endpoint. Use it only from scripts that already accept sending plaintext to your own server.

Do not send secret keys or sensitive plaintext using GET query parameters.

- Algorithm: AES-256-GCM
- KDF: PBKDF2-HMAC-SHA-256
- `encryption_iterations` (default, from `config.json`): 310000
- `max_encryption_iterations` (API security ceiling, from `config.json`): 310000

Omit `iter` to use `encryption_iterations`. The API rejects any iteration count above `max_encryption_iterations`. The iteration count actually used is stored in the versioned payload. Generate a strong secret at `/secret`.

## Encryption design

Browser and PHP use the same construction.

- Algorithm: AES-256-GCM (256-bit key, 12-byte IV, 16-byte authentication tag)
- KDF: PBKDF2-HMAC-SHA-256
- Iterations: `encryption_iterations` from `config.json` (currently 310,000)
- API ceiling: `max_encryption_iterations` from `config.json` (currently 310,000)
- Salt: 16 random bytes
- IV: 12 random bytes, fresh for every encryption
- Authenticated encryption: 128-bit GCM tag, verified on decrypt
- v2 payloads also bind algorithm, KDF, iteration count, salt, and IV as AES-GCM additional authenticated data (AAD), so metadata tampering fails authentication

Argon2id is stronger in principle, but Web Crypto does not provide it. Using Argon2 only on the server would split browser and API formats. PBKDF2 is available in both Web Crypto and PHP, so both sides stay compatible without extra libraries.

The current 310,000 iterations is substantially stronger than the previous 120,000. Decrypt of a versioned payload uses the `iter` stored in that payload and rejects values above `max_encryption_iterations`, so a crafted payload cannot force a more expensive KDF than the API ceiling.

The secret key is never stored and never included in the payload. Anyone who has the secret can decrypt. A long, randomly generated secret is much stronger than a memorable password. Generate one at `/secret`.

This is not a claim that the scheme is unbreakable. Strength depends on secret entropy, implementation correctness, and the attacker’s resources.

### Versioned payload

Encrypt returns JSON:

New encryptions use payload version 2. Version 1 JSON and the older binary blob remain decryptable.

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

Decrypt accepts v2 and v1 JSON. For compatibility, it also accepts the previous binary blob: Base64(`salt || iv || tag || ciphertext`) derived with 120,000 PBKDF2 iterations.

## QR privacy

QR codes are generated locally with a vendored copy of [qrcode-generator](https://github.com/kazuhikoarase/qrcode-generator) in `assets/vendor/`. Unicode is supported. Error correction levels L/M/Q/H are available. PNG and SVG downloads are produced in the browser.

There is no call to `api.qrserver.com` or any other QR service. CSP `connect-src` and `img-src` do not allow third-party QR hosts.

## IP limitations

A server sees one `REMOTE_ADDR` per TCP connection. If the visitor connected over IPv4, IPv6 is “Not detected on this connection”, and the reverse. Dual-stack visibility would require extra infrastructure (separate IPv4 and IPv6 probes, or a trusted reverse proxy that exposes both). This app does not add a third-party lookup just to fill the empty field.

## /health

```text
GET /health
```

HTTP 200 JSON, no cache, no HTML:

```json
{
  "status": "ok",
  "timestamp": "2026-08-13T18:00:00Z",
  "author": "Raju Jha",
  "version": "1.0.0"
}
```

`timestamp` is generated at request time in UTC ISO-8601. `author` and `version` come from `config.json`. The response does not include secrets, filesystem paths, security limits, or debug information.

## config.json

```json
{
  "author": "Raju Jha",
  "version": "1.0.0",
  "security": {
    "bcrypt_cost": 12,
    "max_bcrypt_cost": 14,
    "encryption_iterations": 310000,
    "max_encryption_iterations": 310000
  }
}
```

This file is site metadata and security-limit configuration. Do not put credentials, API keys, or secrets in it. Web access is denied by `.htaccess` and the local router.

- `bcrypt_cost` — default bcrypt cost for generation
- `max_bcrypt_cost` — API security ceiling; requests above this are rejected
- `encryption_iterations` — default PBKDF2-HMAC-SHA-256 iterations for encrypt
- `max_encryption_iterations` — API security ceiling; payloads or `iter` values above this are rejected

`bcrypt_cost` must be `<= max_bcrypt_cost`. `encryption_iterations` must be `<= max_encryption_iterations`. Invalid `config.json` fails safely with a generic error (no filesystem paths or stack traces). If the file is missing, the app uses the same defaults shown above.

## Local development

```bash
git clone https://github.com/rjrajujha/tools.rajujha.dev.git
cd tools.rajujha.dev
npm install
npm run build
php -S 127.0.0.1:8080 router.php
```

Open [http://127.0.0.1:8080](http://127.0.0.1:8080).

```bash
npm run watch:css
```

Tailwind compiles from `src/input.css` into `assets/app.css`. Production hosts do not need Node.js if the compiled CSS and vendored QR files are already present.

PHP’s built-in server must use `router.php`. Do not upload `router.php` as a public Apache endpoint; `.htaccess` already forbids it.

## Production deployment

Runtime is PHP 8.1+ with `mod_rewrite` and OpenSSL. Node.js is only needed to rebuild CSS or refresh the vendored QR library.

1. Run `npm run build` so `assets/app.css` is minified.
2. Upload the app files. Do **not** upload `node_modules/`.
3. Keep `APP_DEBUG` unset or `0` on the server.
4. Serve over HTTPS. Web Crypto on the encryption and hash tools requires a secure context.
5. Confirm `.htaccess` is honoured, or replicate its rewrite rules in the server config.

Required on the host:

- `index.php`, `api.php`, `bootstrap.php`
- `config.json`
- `assets/app.css`, `assets/app.js`
- `assets/vendor/qrcode-generator.js`, `assets/vendor/qrcode-generator-utf8.js`
- `.htaccess`
- `favicon.svg`, `robots.txt`, `sitemap.xml`, `site.webmanifest`

`.htaccess` forbids web access to `router.php`, `bootstrap.php`, `config.json`, `.git`, `.env`, `src/`, `node_modules/`, and common secret/backup files.

## Adding a tool

1. Add the route to `$routes` in `index.php`.
2. Add a homepage card to `$tools`.
3. Add the tool UI: title, description, controls, result, copy/download, privacy note, and the API docs card.
4. Add client logic in `assets/app.js`.
5. Add CSS only when shared components are not enough, then run `npm run build`.
6. Add an API route in `.htaccess`, `router.php`, and `api.php` only if needed. Use POST for sensitive input.
7. Add API docs metadata so the tool gets the same “How to use this tool as an API” card.
8. Update this README, `sitemap.xml`, and the rewrite allow-list.

Keep new tools client-first when possible. Do not store user input.

## Testing

```bash
php -l bootstrap.php
php -l index.php
php -l api.php
php -l router.php
node --check assets/app.js
npm run build
```

Then, with `php -S 127.0.0.1:8080 router.php`:

- `/` and each tool route
- `/health`
- `/api/ip`, `/api/uuid`, `/api/password`
- `POST /api/hash`, `POST /api/base64`, `POST /api/encryption`
- GET to a sensitive endpoint should be 405
- a nonsense URL should render the 404 page
- `config.json` should not be downloadable

Encryption checks worth running: round trip, Unicode/emoji/JSON/long text, wrong key, modified ciphertext/IV/salt/tag, and two encrypts of the same input (ciphertext must differ).

## Security and privacy

- Output is escaped in PHP (`htmlspecialchars`). Markdown rendering escapes HTML and only allows `http(s)` links with a strict character set
- CSP: `default-src 'self'`, `connect-src 'self'`, `img-src 'self' data:`, no third-party scripts
- Headers: `nosniff`, `SAMEORIGIN`, referrer policy, Permissions-Policy
- No application cookies, localStorage, or sessionStorage
- No analytics or tracking
- Secrets and plaintext must not be placed in query strings; sensitive APIs reject GET
- bcrypt cost is capped by `max_bcrypt_cost` in `config.json` (currently 14)
- encryption KDF iterations are capped by `max_encryption_iterations` in `config.json` (currently 310000)
- Regex testing is local, with pattern/flag/input limits
- The encryption UI does not silently POST secrets when Web Crypto is missing
- `APP_DEBUG=1` may include exception detail on error pages. Keep it off in production

Report issues at the GitHub repository.

## License

MIT. See [LICENSE](LICENSE).
