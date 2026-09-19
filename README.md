# tools.rajujha.dev

Fast, private developer utilities. No accounts, no analytics, no database, no tracking.

PHP 8.1+, HTML, compiled Tailwind CSS, and a small JavaScript surface. Most tools run in the browser. Optional JSON APIs exist for scripts. There is no framework and no application-side storage of user input.

## Features

- Clean URLs for every utility
- Browser-first processing whenever it is safe
- JSON APIs for scripting (GET on all endpoints; POST also for hash, hash-validation, base64, encryption, and SSH)
- No cookies, accounts, sessionStorage, or analytics. `localStorage` stores only the theme preference, and only after the user toggles it
- Security headers, CSP, and blocked access to sensitive files
- Light/dark theme with a header toggle; first visit follows the system preference
- Application rate limiting on expensive APIs (20 requests / 60 seconds by default)
- `/health` for uptime checks

## Tools

| Tool | Route | Where it runs | API |
|---|---|---|---|
| DNS Lookup | `/dns` | Browser DoH first; API fallback | `GET /api/dns` |
| QR Code Generator | `/qr` | Browser only, local library | — |
| IP Checker | `/ip` | Server-observed `REMOTE_ADDR` | `GET /api/ip` |
| Encrypt-Decrypt | `/encryption` | Browser Web Crypto only in the UI | `GET` / `POST /api/encryption` |
| Password Generator | `/password` | Browser + optional API | `GET /api/password` |
| Secret Generator | `/secret` | Browser Web Crypto + optional API | `GET /api/secret` |
| UUID Generator | `/uuid` | Browser Web Crypto + optional API | `GET /api/uuid` |
| Timestamp | `/timestamp` | Browser clock + optional API | `GET /api/timestamp` |
| Base64 | `/base64` | Browser + optional API | `GET` / `POST /api/base64` |
| JSON Decoder | `/json` | Browser only | — |
| Markdown Preview | `/markdown` | Browser only | — |
| User-Agent Parser | `/user-agent` | Browser + optional API | `GET /api/user-agent` |
| JWT Decoder | `/jwt` | Browser only | — |
| Hash Validation | `/hash-validation` | Browser for SHA/MD5; API for bcrypt | `GET` / `POST /api/hash-validation` |
| Hash | `/hash` | Browser for SHA-2; API for MD5, SHA-1, bcrypt, all | `GET` / `POST /api/hash` |
| Regex Tester | `/regex` | Browser Web Worker | — |
| Cron Expression Builder | `/cron` | Browser only | — |
| SSH Key Generator | `/ssh` | Browser Web Crypto; API fallback for passphrases | `GET` / `POST /api/ssh` |

## Architecture

`index.php` and `api.php` are thin entry points. Routes, catalog, controllers, services, views, and shared helpers live under `app/`. Public URLs and the JSON envelope (`ok`, `tool`, `data`, `error`) are unchanged.

## Privacy and security

- Output is escaped in PHP. Markdown allows only safe `http(s)` links
- CSP is same-origin except DNS-over-HTTPS (`cloudflare-dns.com`, `dns.google`); no third-party scripts or analytics
- First visit follows `prefers-color-scheme`. Toggling the header sun/moon control writes only `localStorage.theme` (`light` or `dark`)
- Sensitive APIs (`hash`, `hash-validation`, `base64`, `encryption`) accept GET and POST. Prefer POST — secrets and plaintext in GET URLs can be logged or cached
- Encrypt-Decrypt runs locally in the browser when Web Crypto is available; the UI does not silently fall back to the API
- bcrypt and encryption iteration ceilings come from `config.json`
- Application rate limiting protects expensive endpoints; identity uses `REMOTE_ADDR` (hashed on disk). Proxy headers are not trusted by default. Set `client_ip.trust_cloudflare` only when the origin accepts traffic exclusively from Cloudflare

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

Every JSON API response uses this envelope. Result fields live **only** inside `data` — never duplicated at the root.

```json
{
  "ok": true,
  "tool": "uuid",
  "data": {
    "uuid": "..."
  },
  "error": null
}
```

Failure:

```json
{
  "ok": false,
  "tool": "hash",
  "data": null,
  "error": {
    "code": "INVALID_PARAMETER",
    "message": "Unsupported algorithm"
  }
}
```

Sensitive endpoints (`/api/hash`, `/api/hash-validation`, `/api/base64`, `/api/encryption`) accept GET and POST. Prefer POST for secrets — GET query strings can be logged or cached. Request bodies and string inputs are limited to 65,536 bytes.

Safe GET examples: `/api/password`, `/api/uuid`, `/api/secret`, `/api/timestamp`, `/api/ip`, `/api/user-agent`, `/api/dns`, `/api/ssh` without a passphrase. Passphrase-protected SSH keys require POST.

`GET /health` returns UTC status JSON and is not cacheable.

### Hash validation

```text
GET /api/hash-validation?str=admin123&hash=240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9&algorithm=sha256

POST /api/hash-validation
Content-Type: application/json

{"str":"admin123","hash":"240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9","algorithm":"sha256"}
```

`algorithm` is `auto`, `sha256`, `sha384`, `sha512`, `sha1`, `md5`, or `bcrypt`. Auto detects bcrypt, then hex length. Response `data.match` is boolean.

### DNS lookup

The UI has no provider picker. The browser queries JSON DNS-over-HTTPS in this order: Cloudflare (`https://cloudflare-dns.com/dns-query`), then Google Public DNS (`https://dns.google/resolve`), then `GET /api/dns`. Both public resolvers use `GET` with `Accept: application/dns-json`. The JSON shown matches this API (`provider`, `host`, `type`, `status`, `status_name`, `answers`). A missing CORS header is treated as a blocked browser request and is never shown as a raw fetch error. After a CORS failure, that resolver is skipped for the rest of the tab session.

```text
GET /api/dns?host=example.com&type=A
```

`provider` is optional: omit it to try Cloudflare then Google, or set `cloudflare` or `google`. `type` is `A`, `AAAA`, `MX`, `TXT`, `CNAME`, or `NS`. Answers are capped at 8 records.

### SSH keys

```text
GET /api/ssh?algorithm=ed25519

POST /api/ssh
Content-Type: application/json

{"algorithm":"ed25519","comment":"laptop","passphrase":"optional"}
```

`algorithm` is `ed25519`, `rsa2048`, or `rsa4096`. The UI generates keys in the browser when Web Crypto supports the algorithm. A passphrase uses POST so the private key can be encrypted with OpenSSH `aes256-ctr` / `bcrypt`. Keys and passphrases are never stored.

### Encryption

```text
GET /api/encryption?str=hello&key=your-secret&mode=encrypt

POST /api/encryption
Content-Type: application/json

{"str":"hello","key":"your-secret","mode":"encrypt"}
```

- `mode` is `encrypt` or `decrypt`
- Optional `v`: omit or `2` for current (AAD-bound); `v=1` for legacy JSON without AAD; other values are rejected
- Encrypt returns `data.version`, `data.compact`, and `data.json` from one operation
- Algorithm and KDF are fixed (AES-256-GCM, PBKDF2-HMAC-SHA-256). Iteration defaults/ceilings come from `config.json` (currently 310000)
- Decrypt auto-detects compact Base64, V2/V1 JSON, and the older binary blob
- UI always encrypts with V2 and never exposes V1 as a mode

Example encrypt response:

```json
{
  "ok": true,
  "tool": "encryption",
  "data": {
    "mode": "encrypt",
    "version": 2,
    "compact": "...",
    "json": {
      "v": 2,
      "alg": "AES-256-GCM",
      "kdf": "PBKDF2-SHA256",
      "iter": 310000,
      "salt": "<base64>",
      "iv": "<base64>",
      "ct": "<base64>",
      "tag": "<base64>"
    }
  },
  "error": null
}
```

V1 payloads remain decryptable for compatibility. New `v=1` encrypts omit AAD and are not recommended. Decrypt uses the `iter` stored in the payload (subject to the API ceiling).

## Deployment

Runtime: PHP 8.1+ with `mod_rewrite` and OpenSSL. Keep `APP_DEBUG` unset. Serve over HTTPS (Web Crypto needs a secure context). Enable OPcache when available.

Upload `index.php`, `api.php`, `bootstrap.php`, `app/`, `config.json`, compiled assets (including `regex-worker.js` and vendored QR files), `.htaccess`, and static site files. Do not upload `node_modules/`, `tests/`, or `.github/`. Allow the process user to create `var/rate-limit/` (0700). Keep `/app/` blocked from the web server.

Optional: `APP_RATE_LIMIT_DIR` overrides the rate-limit directory. `client_ip.trust_cloudflare` in `config.json` must stay `false` unless the origin accepts traffic only from Cloudflare.

## Testing

```bash
php -l bootstrap.php
php -l index.php
php -l api.php
php -l router.php
find app -name '*.php' -exec php -l {} \;
node --check assets/app.js
node --check assets/theme.js
npm run build
php tests/run.php
php -S 127.0.0.1:8080 router.php
php tests/http.php
```

## License

MIT. See [LICENSE](LICENSE).
