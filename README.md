# tools.rajujha.dev

Fast, private developer utilities. No accounts, no analytics, no database.

Live site: [tools.rajujha.dev](https://tools.rajujha.dev)

The app is intentionally small: PHP, HTML, compiled CSS and a single JavaScript file. Most tools run in the browser. Optional JSON APIs exist for scripts.

## Quick start

```bash
git clone https://github.com/rjrajujha/tools.rajujha.dev.git
cd tools.rajujha.dev
npm install
npm run build
php -S 127.0.0.1:8080 router.php
```

Open [http://127.0.0.1:8080](http://127.0.0.1:8080). Apache and LiteSpeed hosts use `.htaccess` instead of `router.php`.

## What you get

- Clean URLs for every utility
- Browser-first processing whenever it is safe
- JSON APIs for scripting and server-side work
- No cookies, accounts, or application-side storage
- Security headers and blocked access to sensitive files

## Tools

| Tool | Route | Where it runs | API |
|---|---|---|---|
| Password Generator | `/password` | Browser + optional API | `/api/password` |
| Hash | `/hash` | Browser + PHP for legacy/bcrypt | `/api/hash` |
| Timestamp | `/timestamp` | Browser clock + optional API | `/api/timestamp` |
| JSON Decoder | `/json` | Browser | — |
| UUID Generator | `/uuid` | Browser Web Crypto + optional API | `/api/uuid` |
| QR Code Generator | `/qr` | Browser; QR image on demand | — |
| Regex Tester | `/regex` | Browser | — |
| Base64 | `/base64` | Browser + optional API | `/api/base64` |
| JWT Decoder | `/jwt` | Browser | — |
| User-Agent Parser | `/user-agent` | Browser + optional API | `/api/user-agent` |
| Markdown Preview | `/markdown` | Browser | — |
| IP Checker | `/ip` | Server-observed IPv4/IPv6 | `/api/ip` |
| Secret Generator | `/secret` | Browser Web Crypto + optional API | `/api/secret` |
| Encrypt / Decrypt | `/encryption` | Browser AES-256-GCM + optional API | `/api/encryption` |

## Production deploy

Runtime is PHP 8.1+ with `mod_rewrite` (or LiteSpeed equivalent). Node.js is only needed to rebuild CSS.

1. Run `npm run build` so `assets/app.css` is minified.
2. Upload the app files. Do **not** upload `node_modules/`.
3. Keep `APP_DEBUG` unset or `0` on the server.
4. Serve over HTTPS.
5. Confirm `.htaccess` is honoured, or replicate its rewrite rules in the server config.

Required on the host:

- `index.php`, `api.php`, `bootstrap.php`
- `assets/app.css`, `assets/app.js`
- `.htaccess`
- `favicon.svg`, `robots.txt`, `sitemap.xml`, `site.webmanifest`

Do not expose `router.php` on Apache/LiteSpeed. `.htaccess` already forbids web access to `router.php`, `bootstrap.php`, `.git`, `.env`, `src/`, `node_modules/`, and common secret/backup files.

Quick sanity checks after deploy:

```bash
php -l bootstrap.php
php -l index.php
php -l api.php
php -l router.php
```

Then open `/`, a tool page, `/api/ip`, and a nonsense URL to confirm the 404 page.

## Development

```bash
npm run watch:css
php -S 127.0.0.1:8080 router.php
```

Tailwind compiles from `src/input.css` into `assets/app.css`. Production hosts do not need Node.js if the compiled CSS is committed.

## API

All API routes return JSON and use HTTP status codes for invalid requests.

Success:

```json
{
  "ok": true,
  "tool": "hash"
}
```

Error:

```json
{
  "ok": false,
  "error": "A useful error message"
}
```

### Password

```text
GET /api/password?length=24&upper=1&lower=1&numbers=1&symbols=1&count=1
```

Cryptographically random passwords via PHP `random_int`. `length` is 8–128, `count` is 1–20. Boolean flags accept `1`/`0` or `true`/`false`. The UI generates locally; the API is optional and stores nothing.

### Hash

```text
GET /api/hash?str=admin123&algorithm=sha256
GET /api/hash?str=admin123&algorithm=bcrypt&cost=12
GET /api/hash?str=admin123&algorithm=all
```

Algorithms: `md5`, `sha1`, `sha256`, `sha384`, `sha512`, `bcrypt`, `all`. bcrypt uses a fresh random salt. `cost` is 4–31, default 12.

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
GET /api/base64?str=hello&mode=encode
GET /api/base64?str=aGVsbG8=&mode=decode
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

Reports server-observed `REMOTE_ADDR` as IPv4 or IPv6. A single TCP connection is only one family, so the other field is `null`. Proxy headers are exposed separately and are not trusted as the visitor’s real IP.

### Encryption

AES-256-GCM with PBKDF2-SHA-256. Each encrypt creates a new random salt and IV.

```text
GET /api/encryption?str=hello&key=change-me&mode=encrypt
GET /api/encryption?str=ENCRYPTED_VALUE&key=change-me&mode=decrypt
```

Do not put real passwords, private keys or sensitive plaintext in GET query strings. URLs are logged. Use a POST API for sensitive production integrations.

## Privacy model

Interactive tools prefer local processing:

- Password, UUID, Secret: Web Crypto / `crypto.getRandomValues`
- Hash: Web Crypto for SHA-256/384/512; MD5, SHA-1, bcrypt, and `all` use the API
- Encrypt / Decrypt: Web Crypto AES-256-GCM; plaintext and keys stay in the browser unless you call the API
- Timestamp clock, JSON, Regex, JWT, User-Agent, Markdown, Base64 UI: browser-local
- Optional JSON APIs do not store data

QR generation calls an external image endpoint only when you click Generate.

## Encryption format

Browser encryption:

- PBKDF2-SHA-256, 120,000 iterations, 256-bit key
- AES-GCM
- 16-byte salt, 12-byte IV
- Payload: Base64(`salt || iv || ciphertext+tag`)

The PHP API uses the same cryptographic choices. Its payload stores the GCM tag explicitly so OpenSSL can decrypt it.

## Routing and security

`.htaccess` provides:

- Clean URLs for tools and `/api/<tool>`
- Disabled directory listing
- Blocked access to `.git`, `.env`, Composer/npm metadata, backups and logs
- `nosniff`, frame protection, referrer policy, Permissions-Policy and a restrictive CSP

The app does not need a database.

`bootstrap.php` handles errors for HTML and JSON:

- Unknown routes render a 404 page
- Uncaught exceptions and fatals render a 500 page
- API requests return `{ "ok": false, "error": "..." }`
- Set `APP_DEBUG=1` only while debugging. Keep it off in production.

## Adding a tool

1. Add the route to `$routes` in `index.php`.
2. Add a homepage card to `$tools`.
3. Add the tool UI to the page switch.
4. Add client logic in `assets/app.js`.
5. Add CSS only when shared components are not enough, then run `npm run build`.
6. Add an API route in `.htaccess` and `api.php` only if needed.
7. Add API docs metadata so the tool gets the same “How to use this tool as an API” card.
8. Update this README, `sitemap.xml`, and the rewrite allow-list.

## License

MIT. See [LICENSE](LICENSE).
