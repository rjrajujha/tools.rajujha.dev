<?php
declare(strict_types=1);

namespace App;

final class Catalog
{
    /**
     * @return array<string, string>
     */
    public static function routes(): array
    {
        return [
            '/' => 'home',
            '/password' => 'password',
            '/hash' => 'hash',
            '/timestamp' => 'timestamp',
            '/json' => 'json',
            '/uuid' => 'uuid',
            '/qr' => 'qr',
            '/regex' => 'regex',
            '/base64' => 'base64',
            '/jwt' => 'jwt',
            '/user-agent' => 'user-agent',
            '/markdown' => 'markdown',
            '/ip' => 'ip',
            '/secret' => 'secret',
            '/encryption' => 'encryption',
            '/hash-validation' => 'hash-validation',
            '/cron' => 'cron',
            '/ssh' => 'ssh',
            '/dns' => 'dns',
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function tools(): array
    {
        return [
            ['password', 'Password Generator', 'Create strong random passwords'],
            ['hash', 'Hash', 'Hash text with SHA, MD5 or bcrypt'],
            ['timestamp', 'Timestamp', 'Convert Unix time and see current UTC'],
            ['json', 'JSON Decoder', 'Format, validate and inspect JSON'],
            ['uuid', 'UUID Generator', 'Generate UUID v4 identifiers locally'],
            ['qr', 'QR Code Generator', 'Create QR codes in your browser'],
            ['regex', 'Regex Tester', 'Test regular expressions safely'],
            ['base64', 'Base64', 'Encode and decode Base64 text'],
            ['jwt', 'JWT Decoder', 'Decode JWT header and payload locally'],
            ['user-agent', 'User-Agent Parser', 'Inspect browser and device information'],
            ['markdown', 'Markdown Preview', 'Preview Markdown instantly in your browser'],
            ['ip', 'IP Checker', 'See the IP address observed by this server'],
            ['secret', 'Secret Generator', 'Generate cryptographic random secrets'],
            ['encryption', 'Encrypt-Decrypt', 'Encrypt and decrypt text with a secret key'],
            ['hash-validation', 'Hash Validation', 'Check a string against SHA, MD5 or bcrypt'],
            ['cron', 'Cron Expression Builder', 'Build and preview cron schedules'],
            ['ssh', 'SSH Key Generator', 'Create Ed25519 and RSA keys locally'],
            ['dns', 'DNS Lookup', 'Look up A, AAAA, MX, TXT, CNAME and NS'],
        ];
    }

    public static function pageForPath(string $normalizedPath): ?string
    {
        return self::routes()[$normalizedPath] ?? null;
    }

    /**
     * @return array{0: string, 1: string, 2: string}|null
     */
    public static function tool(string $page): ?array
    {
        foreach (self::tools() as $tool) {
            if ($tool[0] === $page) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: ?string, 3: string}>
     */
    public static function meta(): array
    {
        $security = app_security();
        $bcryptCost = $security['bcrypt_cost'];
        $maxBcryptCost = $security['max_bcrypt_cost'];
        $encIter = $security['encryption_iterations'];
        $maxEncIter = $security['max_encryption_iterations'];

        return [
            'password' => [
                '/api/password',
                'GET',
                'length, upper, lower, numbers, symbols',
                'Optional API for scripts. UI stays in the browser.',
            ],
            'hash' => [
                '/api/hash',
                'GET, POST',
                'str, algorithm, cost?',
                'GET and POST. UI handles SHA-256/384/512 locally; API covers MD5, SHA-1, bcrypt, and all. Default bcrypt cost '
                    . $bcryptCost . ' (max ' . $maxBcryptCost . '). GET query strings can be logged or cached — prefer POST for secrets.',
            ],
            'timestamp' => [
                '/api/timestamp',
                'GET',
                'timestamp?, unit?',
                'Omit params for current UTC, or pass a Unix timestamp to convert.',
            ],
            'json' => [null, null, null, 'Browser-only. No server API.'],
            'uuid' => [
                '/api/uuid',
                'GET',
                'count?',
                'Optional API for scripts. UI stays in the browser.',
            ],
            'qr' => [null, null, null, 'Browser-only. No server API.'],
            'regex' => [null, null, null, 'Browser-only. No server API.'],
            'base64' => [
                '/api/base64',
                'GET, POST',
                'str, mode',
                'GET and POST. mode is encode or decode. UI can also run locally. GET query strings can be logged or cached — prefer POST for secrets.',
            ],
            'jwt' => [null, null, null, 'Browser-only. Decoding does not verify signatures.'],
            'user-agent' => [
                '/api/user-agent',
                'GET',
                'ua?',
                'Uses the request User-Agent, or pass ua=… explicitly.',
            ],
            'markdown' => [null, null, null, 'Browser-only. No server API.'],
            'ip' => [
                '/api/ip',
                'GET',
                '—',
                'Returns REMOTE_ADDR for this connection. Proxy headers are listed and not trusted.',
            ],
            'secret' => [
                '/api/secret',
                'GET',
                'length, format, count?',
                'Optional API for scripts. UI stays in the browser.',
            ],
            'encryption' => [
                '/api/encryption',
                'GET, POST',
                'str, key, mode, v?, iter?',
                'GET and POST. UI stays in the browser. Default v=2; v=1 is legacy. Returns data.compact and data.json. '
                    . 'PBKDF2 iterations default ' . number_format($encIter) . ' (max ' . number_format($maxEncIter) . '). '
                    . 'GET query strings can be logged or cached — prefer POST for secrets.',
            ],
            'hash-validation' => [
                '/api/hash-validation',
                'GET, POST',
                'str, hash, algorithm?',
                'GET and POST. UI validates SHA-256/384/512, SHA-1, and MD5 locally; bcrypt uses POST. algorithm is auto, sha256, sha384, sha512, sha1, md5, or bcrypt. GET query strings can be logged or cached — prefer POST for secrets.',
            ],
            'cron' => [null, null, null, 'Browser-only. No server API.'],
            'ssh' => [
                '/api/ssh',
                'GET, POST',
                'algorithm?, comment?, passphrase?',
                'Optional API for scripts. UI prefers Web Crypto, and uses POST when a passphrase is set. algorithm is ed25519, rsa2048, or rsa4096. Keys and passphrases are never stored.',
            ],
            'dns' => [
                '/api/dns',
                'GET',
                'host, type?, provider?',
                'Looks up DNS records in the browser first (JSON DoH for Cloudflare, RFC 8484 for the default provider). A CORS or network failure retries the other provider, then this API. Answers are decoded JSON, not raw DNS wire. provider is default or cloudflare. type is A, AAAA, MX, TXT, CNAME, or NS. Up to 8 records are returned.',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function examples(): array
    {
        return [
            'password' => 'GET /api/password?length=24&upper=1&lower=1&numbers=1&symbols=1',
            'hash' => "GET /api/hash?str=admin123&algorithm=sha256\n\nPOST /api/hash\nContent-Type: application/json\n\n{\"str\":\"admin123\",\"algorithm\":\"sha256\"}",
            'timestamp' => 'GET /api/timestamp?timestamp=1755000000&unit=s',
            'uuid' => 'GET /api/uuid?count=1',
            'base64' => "GET /api/base64?str=hello&mode=encode\n\nPOST /api/base64\nContent-Type: application/json\n\n{\"str\":\"hello\",\"mode\":\"encode\"}",
            'user-agent' => 'GET /api/user-agent',
            'ip' => 'GET /api/ip',
            'secret' => 'GET /api/secret?length=48&format=hex',
            'encryption' => "GET /api/encryption?str=hello&key=your-secret&mode=encrypt\n\nPOST /api/encryption\nContent-Type: application/json\n\n{\"str\":\"hello\",\"key\":\"your-secret\",\"mode\":\"encrypt\"}",
            'hash-validation' => "GET /api/hash-validation?str=admin123&hash=240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9&algorithm=sha256\n\nPOST /api/hash-validation\nContent-Type: application/json\n\n{\"str\":\"admin123\",\"hash\":\"240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9\",\"algorithm\":\"sha256\"}",
            'ssh' => "GET /api/ssh?algorithm=ed25519\n\nPOST /api/ssh\nContent-Type: application/json\n\n{\"algorithm\":\"ed25519\",\"comment\":\"laptop\",\"passphrase\":\"optional\"}",
            'dns' => 'GET /api/dns?host=example.com&type=A&provider=default',
        ];
    }

    public static function icon(string $slug, string $sizeClass = 'size-5'): string
    {
        $inner = [
            'password' => '<circle cx="8" cy="11" r="3.5"/><path d="M11.5 11H20v2.5M16 11v2.5M19 11v4"/>',
            'hash' => '<path d="M9 4 7 20M17 4l-2 16M4 9h16M3 15h16"/>',
            'timestamp' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4.5l3 1.5"/>',
            'json' => '<path d="M8 5c-2.2 0-3 1.6-3 3.2v1.6c0 1.1-.8 1.7-2 1.7 1.2 0 2 .6 2 1.7v1.6c0 1.6.8 3.2 3 3.2"/><path d="M16 5c2.2 0 3 1.6 3 3.2v1.6c0 1.1.8 1.7 2 1.7-1.2 0-2 .6-2 1.7v1.6c0 1.6-.8 3.2-3 3.2"/>',
            'uuid' => '<rect x="4.5" y="4.5" width="15" height="15" rx="3"/><path d="M8 9h8M8 12h5M8 15h6"/>',
            'qr' => '<rect x="4" y="4" width="6.5" height="6.5" rx="1"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1"/><path d="M14 14h3v3h-3zM18.5 14H20v6h-6v-1.5h4.5z"/>',
            'regex' => '<path d="M7 18 15 6"/><path d="M16 14.5h4M18 12.5v4"/><circle cx="18" cy="16.5" r="0.4" fill="currentColor" stroke="none"/>',
            'base64' => '<rect x="4" y="5" width="16" height="14" rx="2"/><path d="M8 9h8M8 12h5M8 15h7"/>',
            'jwt' => '<rect x="6" y="4.5" width="12" height="4" rx="1"/><rect x="5" y="10" width="14" height="4" rx="1"/><rect x="6" y="15.5" width="12" height="4" rx="1"/>',
            'user-agent' => '<rect x="3.5" y="5" width="17" height="11" rx="2"/><path d="M8 20h8M12 16v4"/>',
            'markdown' => '<rect x="5" y="3.5" width="14" height="17" rx="2"/><path d="M8 15.5v-7l2.2 3.2L12.4 8.5v7M15 8.5v7l2.2-3"/>',
            'ip' => '<circle cx="12" cy="12" r="8"/><path d="M4 12h16M12 4c2.8 2.8 2.8 13.2 0 16M12 4c-2.8 2.8-2.8 13.2 0 16"/>',
            'secret' => '<path d="M12 3.5 19 7v4.5c0 4.4-3.1 7.3-7 8.2-3.9-.9-7-3.8-7-8.2V7l7-3.5z"/><circle cx="12" cy="11" r="1.6"/><path d="M12 12.6V15"/>',
            'encryption' => '<rect x="6" y="11" width="12" height="9" rx="2"/><path d="M8.5 11V8.2a3.5 3.5 0 0 1 7 0V11"/><path d="M12 14.2v2.2"/>',
            'hash-validation' => '<path d="M9 4 7 20M17 4l-2 16M4 9h16M3 15h16"/><path d="M16.5 16.5 18 18l3.5-4"/>',
            'cron' => '<rect x="4" y="6" width="16" height="14" rx="2"/><path d="M8 4v4M16 4v4M4 11h16"/>',
            'ssh' => '<circle cx="8" cy="12" r="3.5"/><path d="M11.5 12H20v2.5M16.5 12v3M19 12v4"/>',
            'dns' => '<ellipse cx="12" cy="7" rx="8" ry="3"/><path d="M4 7v10c0 1.7 3.6 3 8 3s8-1.3 8-3V7"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
        ];

        $markup = $inner[$slug] ?? '<circle cx="12" cy="12" r="7"/>';

        return '<svg class="' . $sizeClass . ' fill-none stroke-current [stroke-linecap:round] [stroke-linejoin:round] [stroke-width:1.8]" viewBox="0 0 24 24" aria-hidden="true">'
            . $markup
            . '</svg>';
    }
}
