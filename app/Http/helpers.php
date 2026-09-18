<?php
declare(strict_types=1);

const APP_SENSITIVE_TOOLS = ['hash', 'base64', 'encryption', 'hash-validation', 'ssh'];
const APP_RATE_LIMITED_TOOLS = ['hash', 'encryption', 'password', 'secret', 'uuid', 'hash-validation', 'dns', 'ssh'];

function out(array $payload, int $status = 200): never
{
    http_response_code($status);

    $ok = array_key_exists('ok', $payload) ? (bool) $payload['ok'] : $status < 400;
    $data = $payload['data'] ?? ($ok ? new stdClass() : null);

    echo app_json_encode([
        'ok' => $ok,
        'tool' => $payload['tool'] ?? null,
        'data' => $data,
        'error' => $payload['error'] ?? null,
    ]);
    exit;
}

function ok(string $tool, array $data): never
{
    out([
        'ok' => true,
        'tool' => $tool,
        'data' => $data,
        'error' => null,
    ]);
}

function fail(string $message, int $status = 400, ?string $tool = null, ?string $code = null): never
{
    out([
        'ok' => false,
        'tool' => $tool,
        'data' => null,
        'error' => app_api_error($code ?? app_error_code_for_status($status), $message),
    ], $status);
}

function reject_method(string $tool, array $allowed, string $message): never
{
    if (!headers_sent()) {
        header('Allow: ' . implode(', ', $allowed));
    }

    fail($message, 405, $tool !== '' ? $tool : null);
}

function request_tool(): string
{
    static $resolved = null;

    if ($resolved !== null) {
        return $resolved;
    }

    $query = strtolower(trim((string) ($_GET['tool'] ?? '')));
    if ($query !== '') {
        $resolved = $query;
        return $resolved;
    }

    if (request_method() === 'POST') {
        $body = body_params();
        $resolved = strtolower(trim((string) ($body['tool'] ?? '')));
        return $resolved;
    }

    $resolved = '';
    return $resolved;
}

function request_method(): string
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function require_post(string $tool): void
{
    if (request_method() === 'POST') {
        return;
    }

    reject_method(
        $tool,
        ['POST'],
        'This endpoint requires POST. Do not send secrets or plaintext in query strings.'
    );
}

function require_http_method(string $tool): void
{
    $method = request_method();

    if (in_array($method, ['GET', 'POST', 'HEAD'], true)) {
        return;
    }

    reject_method($tool, ['GET', 'POST'], 'Method not allowed');
}

function assert_size(string $value, string $name = 'input'): void
{
    if (strlen($value) > APP_MAX_INPUT_BYTES) {
        fail($name . ' exceeds the maximum size of ' . APP_MAX_INPUT_BYTES . ' bytes', 413);
    }
}

function body_params(): array
{
    static $cached = null;
    static $loading = false;

    if ($cached !== null) {
        return $cached;
    }

    if ($loading) {
        return [];
    }

    $loading = true;

    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > APP_MAX_INPUT_BYTES + 4096) {
        fail('Request body is too large', 413);
    }

    $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
    $contentType = str_contains($contentType, ';')
        ? trim(explode(';', $contentType, 2)[0])
        : $contentType;

    if ($contentType === 'application/json') {
        $raw = (string) file_get_contents('php://input');
        if (strlen($raw) > APP_MAX_INPUT_BYTES + 4096) {
            fail('Request body is too large', 413);
        }
        if ($raw === '') {
            $cached = [];
            $loading = false;
            return $cached;
        }

        if (str_contains($raw, "\0")) {
            fail('Invalid JSON body', 400, request_tool() ?: null, 'INVALID_JSON');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || array_is_list($decoded)) {
            fail('Invalid JSON body', 400, request_tool() ?: null, 'INVALID_JSON');
        }

        $cached = $decoded;
        $loading = false;
        return $cached;
    }

    $tool = strtolower(trim((string) ($_GET['tool'] ?? '')));
    $formTypes = ['', 'application/x-www-form-urlencoded', 'multipart/form-data'];
    if (in_array($contentType, $formTypes, true)) {
        $cached = $_POST;
        $loading = false;
        return $cached;
    }

    if (in_array($tool, APP_SENSITIVE_TOOLS, true)) {
        fail('Content-Type must be application/json or application/x-www-form-urlencoded', 415, $tool);
    }

    $cached = $_POST;
    $loading = false;
    return $cached;
}

function request_params(): array
{
    static $params = null;

    if ($params !== null) {
        return $params;
    }

    $tool = request_tool();
    $params = $_GET;

    if (request_method() === 'POST') {
        $params = array_merge($params, body_params());
    }

    if ($tool !== '') {
        $params['tool'] = $tool;
    }

    return $params;
}

function param(string $key, string $fallback = ''): string
{
    $params = request_params();

    if (!array_key_exists($key, $params)) {
        return $fallback;
    }

    $value = $params[$key];
    if (is_bool($value)) {
        $value = $value ? '1' : '0';
    } elseif (is_int($value) || is_float($value)) {
        if (is_float($value) && (is_nan($value) || is_infinite($value) || $value !== floor($value))) {
            fail("Parameter {$key} must be a string or integer", 400, request_tool() ?: null);
        }
        $value = (string) $value;
    } elseif (!is_string($value)) {
        fail("Parameter {$key} must be a string", 400, request_tool() ?: null);
    }

    if (str_contains($value, "\0")) {
        fail("Parameter {$key} contains invalid characters", 400, request_tool() ?: null);
    }

    return $value;
}

function inputValue(): string
{
    return param('str', param('string', param('input')));
}

function requireInput(): string
{
    $params = request_params();

    if (!isset($params['str']) && !isset($params['string']) && !isset($params['input'])) {
        fail('Missing parameter: str', 400, request_tool() ?: null, 'MISSING_PARAMETER');
    }

    $value = inputValue();
    assert_size($value, 'str');

    return $value;
}

function boolParam(string $key, bool $default = true): bool
{
    $params = request_params();

    if (!array_key_exists($key, $params)) {
        return $default;
    }

    $value = $params[$key];
    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value) && ($value === 0 || $value === 1)) {
        return $value === 1;
    }

    if (!is_string($value) && !is_int($value)) {
        fail("Parameter {$key} must be a boolean", 400, request_tool() ?: null);
    }

    $normalized = strtolower(trim((string) $value));

    if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    fail("Parameter {$key} must be a boolean", 400, request_tool() ?: null);
}

function classifyIp(string $ip): ?string
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return 'ipv4';
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return 'ipv6';
    }

    return null;
}

function parseUserAgent(string $ua): array
{
    $browser = 'Unknown';
    $version = '';

    if (preg_match('/Edg\/([\d.]+)/', $ua, $m)) {
        $browser = 'Edge';
        $version = $m[1];
    } elseif (preg_match('/OPR\/([\d.]+)/', $ua, $m)) {
        $browser = 'Opera';
        $version = $m[1];
    } elseif (preg_match('/Firefox\/([\d.]+)/', $ua, $m)) {
        $browser = 'Firefox';
        $version = $m[1];
    } elseif (preg_match('/Chrome\/([\d.]+)/', $ua, $m)) {
        $browser = 'Chrome';
        $version = $m[1];
    } elseif (preg_match('/Version\/([\d.]+).*Safari\//', $ua, $m) || preg_match('/Safari\/([\d.]+)/', $ua, $m)) {
        $browser = 'Safari';
        $version = $m[1];
    }

    if (str_contains($ua, 'Windows')) {
        $os = 'Windows';
    } elseif (str_contains($ua, 'Android')) {
        $os = 'Android';
    } elseif (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) {
        $os = 'iOS';
    } elseif (str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh')) {
        $os = 'macOS';
    } elseif (str_contains($ua, 'Linux')) {
        $os = 'Linux';
    } else {
        $os = 'Unknown';
    }

    $device = preg_match('/Mobi|Android|iPhone|iPad/i', $ua) ? 'Mobile/Tablet' : 'Desktop';

    return [
        'user_agent' => $ua,
        'browser' => $browser,
        'version' => $version,
        'os' => $os,
        'device' => $device,
        'mobile' => $device !== 'Desktop',
    ];
}

function generateUuidV4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    $hex = bin2hex($bytes);

    return substr($hex, 0, 8) . '-'
        . substr($hex, 8, 4) . '-'
        . substr($hex, 12, 4) . '-'
        . substr($hex, 16, 4) . '-'
        . substr($hex, 20, 12);
}

function generateSecret(int $length, string $format): string
{
    if ($format === 'hex') {
        return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
    }

    $byteCount = (int) ceil($length * 0.75);
    $encoded = base64_encode(random_bytes(max(1, $byteCount)));

    if ($format === 'base64url') {
        $encoded = rtrim(strtr($encoded, '+/', '-_'), '=');
    }

    return substr($encoded, 0, $length);
}

function generatePassword(
    int $length,
    bool $upper,
    bool $lower,
    bool $numbers,
    bool $symbols
): string {
    $sets = [];

    if ($upper) {
        $sets[] = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    }
    if ($lower) {
        $sets[] = 'abcdefghijklmnopqrstuvwxyz';
    }
    if ($numbers) {
        $sets[] = '0123456789';
    }
    if ($symbols) {
        $sets[] = '!@#$%^&*()-_=+[]{}';
    }

    if ($sets === []) {
        fail('Select at least one character set', 400, 'password');
    }

    $alphabet = implode('', $sets);
    $alphabetLength = strlen($alphabet);
    $password = '';

    if ($length >= count($sets)) {
        foreach ($sets as $set) {
            $password .= $set[random_int(0, strlen($set) - 1)];
        }
    }

    while (strlen($password) < $length) {
        $password .= $alphabet[random_int(0, $alphabetLength - 1)];
    }

    $chars = str_split($password);
    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
    }

    return implode('', array_slice($chars, 0, $length));
}

function b64_decode_strict(string $value): string|false
{
    $clean = preg_replace('/\s+/', '', $value) ?? '';

    return base64_decode($clean, true);
}

function derive_aes_key(string $secret, string $salt, int $iterations): string
{
    $max = app_security()['max_encryption_iterations'];

    if ($iterations < APP_PBKDF2_ITER_MIN || $iterations > $max) {
        fail('Unsupported key-derivation parameters', 400, 'encryption');
    }

    return hash_pbkdf2('sha256', $secret, $salt, $iterations, 32, true);
}

function encryption_aad(int $version, string $alg, string $kdf, int $iterations, string $saltB64, string $ivB64): string
{
    return implode('|', [
        (string) $version,
        strtoupper($alg),
        strtoupper($kdf),
        (string) $iterations,
        $saltB64,
        $ivB64,
    ]);
}

/**
 * Compact opaque Base64 encoding of one encrypted payload.
 * Binary layout: "TJ" | version(u8) | iter(u32 BE) | salt(16) | iv(12) | tag(16) | ct
 */
function encrypt_compact_encode(array $payload): string
{
    $version = filter_var($payload['v'] ?? null, FILTER_VALIDATE_INT);
    $iterations = filter_var($payload['iter'] ?? null, FILTER_VALIDATE_INT);
    $salt = isset($payload['salt']) ? b64_decode_strict((string) $payload['salt']) : false;
    $iv = isset($payload['iv']) ? b64_decode_strict((string) $payload['iv']) : false;
    $ct = isset($payload['ct']) ? b64_decode_strict((string) $payload['ct']) : false;
    $tag = isset($payload['tag']) ? b64_decode_strict((string) $payload['tag']) : false;

    if (
        $version === false
        || $iterations === false
        || $salt === false
        || $iv === false
        || $ct === false
        || $tag === false
        || !in_array($version, [APP_ENC_VERSION_V1, APP_ENC_VERSION], true)
        || $iterations < APP_PBKDF2_ITER_MIN
        || $iterations > APP_PBKDF2_ITER_ABS_MAX
        || strlen($salt) !== APP_ENC_SALT_BYTES
        || strlen($iv) !== APP_ENC_IV_BYTES
        || strlen($tag) !== APP_ENC_TAG_BYTES
    ) {
        fail('Unable to encode compact encrypted payload', 500, 'encryption');
    }

    $binary = APP_ENC_COMPACT_MAGIC
        . chr($version)
        . pack('N', $iterations)
        . $salt
        . $iv
        . $tag
        . $ct;

    return base64_encode($binary);
}

function encrypt_compact_decode(string $binary): ?array
{
    if (strlen($binary) < APP_ENC_COMPACT_HEADER_BYTES) {
        return null;
    }

    if (!str_starts_with($binary, APP_ENC_COMPACT_MAGIC)) {
        return null;
    }

    $version = ord($binary[2]);
    if (!in_array($version, [APP_ENC_VERSION_V1, APP_ENC_VERSION], true)) {
        return null;
    }

    $unpacked = unpack('Niter', substr($binary, 3, 4));
    if ($unpacked === false) {
        return null;
    }

    $iterations = (int) $unpacked['iter'];
    $salt = substr($binary, 7, APP_ENC_SALT_BYTES);
    $iv = substr($binary, 23, APP_ENC_IV_BYTES);
    $tag = substr($binary, 35, APP_ENC_TAG_BYTES);
    $ct = substr($binary, 51);

    if (
        $iterations < APP_PBKDF2_ITER_MIN
        || $iterations > APP_PBKDF2_ITER_ABS_MAX
        || strlen($salt) !== APP_ENC_SALT_BYTES
        || strlen($iv) !== APP_ENC_IV_BYTES
        || strlen($tag) !== APP_ENC_TAG_BYTES
    ) {
        return null;
    }

    return [
        'v' => $version,
        'alg' => 'AES-256-GCM',
        'kdf' => 'PBKDF2-SHA256',
        'iter' => $iterations,
        'salt' => base64_encode($salt),
        'iv' => base64_encode($iv),
        'ct' => base64_encode($ct),
        'tag' => base64_encode($tag),
    ];
}

function encrypt_payload(string $plaintext, string $secret, int $iterations, int $version = APP_ENC_VERSION): array
{
    if (!in_array($version, [APP_ENC_VERSION_V1, APP_ENC_VERSION], true)) {
        fail('Unsupported encrypted payload', 400, 'encryption');
    }

    $salt = random_bytes(APP_ENC_SALT_BYTES);
    $iv = random_bytes(APP_ENC_IV_BYTES);
    $derived = derive_aes_key($secret, $salt, $iterations);
    $saltB64 = base64_encode($salt);
    $ivB64 = base64_encode($iv);
    $aad = $version === APP_ENC_VERSION
        ? encryption_aad($version, 'AES-256-GCM', 'PBKDF2-SHA256', $iterations, $saltB64, $ivB64)
        : '';
    $tag = '';
    $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $derived, OPENSSL_RAW_DATA, $iv, $tag, $aad);

    if ($cipher === false || strlen($tag) !== APP_ENC_TAG_BYTES) {
        fail('Encryption failed', 500, 'encryption');
    }

    return [
        'v' => $version,
        'alg' => 'AES-256-GCM',
        'kdf' => 'PBKDF2-SHA256',
        'iter' => $iterations,
        'salt' => $saltB64,
        'iv' => $ivB64,
        'ct' => base64_encode($cipher),
        'tag' => base64_encode($tag),
    ];
}

function decrypt_payload(string $input, string $secret): string
{
    $trimmed = trim($input);
    $decodedJson = json_decode($trimmed, true);

    if (is_array($decodedJson)) {
        return decrypt_versioned($decodedJson, $secret);
    }

    $raw = b64_decode_strict($trimmed);
    if ($raw !== false) {
        $compact = encrypt_compact_decode($raw);
        if (is_array($compact)) {
            return decrypt_versioned($compact, $secret);
        }
    }

    return decrypt_legacy($trimmed, $secret);
}

function decrypt_versioned(array $payload, string $secret): string
{
    $version = filter_var($payload['v'] ?? null, FILTER_VALIDATE_INT);
    $alg = strtoupper((string) ($payload['alg'] ?? ''));
    $kdf = strtoupper((string) ($payload['kdf'] ?? ''));

    if (
        !in_array($version, [APP_ENC_VERSION_V1, APP_ENC_VERSION], true)
        || $alg !== 'AES-256-GCM'
        || $kdf !== 'PBKDF2-SHA256'
    ) {
        fail('Unsupported encrypted payload', 400, 'encryption');
    }

    $iterations = filter_var($payload['iter'] ?? null, FILTER_VALIDATE_INT);
    $saltB64 = isset($payload['salt']) ? trim((string) $payload['salt']) : '';
    $ivB64 = isset($payload['iv']) ? trim((string) $payload['iv']) : '';
    $salt = $saltB64 !== '' ? b64_decode_strict($saltB64) : false;
    $iv = $ivB64 !== '' ? b64_decode_strict($ivB64) : false;
    $ct = isset($payload['ct']) ? b64_decode_strict((string) $payload['ct']) : false;
    $tag = isset($payload['tag']) ? b64_decode_strict((string) $payload['tag']) : false;

    if (
        $iterations === false
        || $salt === false
        || $iv === false
        || $ct === false
        || $tag === false
        || strlen($salt) !== APP_ENC_SALT_BYTES
        || strlen($iv) !== APP_ENC_IV_BYTES
        || strlen($tag) !== APP_ENC_TAG_BYTES
    ) {
        fail('Invalid encrypted payload', 400, 'encryption');
    }

    $derived = derive_aes_key($secret, $salt, $iterations);
    $aad = $version === APP_ENC_VERSION
        ? encryption_aad($version, $alg, $kdf, $iterations, $saltB64, $ivB64)
        : '';

    try {
        $plain = openssl_decrypt($ct, 'aes-256-gcm', $derived, OPENSSL_RAW_DATA, $iv, $tag, $aad);
    } catch (Throwable) {
        $plain = false;
    }

    if ($plain === false) {
        fail('Decryption failed. Check the secret key and encrypted value.', 400, 'encryption', 'DECRYPTION_FAILED');
    }

    return $plain;
}

function decrypt_legacy(string $input, string $secret): string
{
    $raw = b64_decode_strict($input);
    if ($raw === false || strlen($raw) < 44) {
        fail('Invalid encrypted payload', 400, 'encryption');
    }

    $salt = substr($raw, 0, 16);
    $iv = substr($raw, 16, 12);
    $tag = substr($raw, 28, 16);
    $cipher = substr($raw, 44);
    $derived = derive_aes_key($secret, $salt, APP_LEGACY_PBKDF2_ITERATIONS);

    try {
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $derived, OPENSSL_RAW_DATA, $iv, $tag);
    } catch (Throwable) {
        $plain = false;
    }

    if ($plain === false) {
        fail('Decryption failed. Check the secret key and encrypted value.', 400, 'encryption', 'DECRYPTION_FAILED');
    }

    return $plain;
}

function encryption_request_version(): int
{
    $params = request_params();
    if (!array_key_exists('v', $params)) {
        return APP_ENC_VERSION;
    }

    $raw = $params['v'];
    if (is_int($raw) && in_array($raw, [APP_ENC_VERSION_V1, APP_ENC_VERSION], true)) {
        return $raw;
    }

    if (is_string($raw) && ctype_digit($raw)) {
        $parsed = (int) $raw;
        if (
            in_array($parsed, [APP_ENC_VERSION_V1, APP_ENC_VERSION], true)
            && (string) $parsed === $raw
        ) {
            return $parsed;
        }
    }

    fail('v must be 1 or 2', 400, 'encryption');
}

function looks_like_bcrypt(string $hash): bool
{
    return (bool) preg_match('/^\$2[abxy]\$\d{2}\$[A-Za-z0-9.\/]{53}$/', $hash);
}

function detect_digest_algorithm(string $hash): ?string
{
    $hex = preg_replace('/^0x/i', '', trim($hash)) ?? '';
    if (!preg_match('/^[0-9a-fA-F]+$/', $hex)) {
        return null;
    }

    return match (strlen($hex)) {
        32 => 'md5',
        40 => 'sha1',
        64 => 'sha256',
        96 => 'sha384',
        128 => 'sha512',
        default => null,
    };
}

function hash_validation_compare(string $str, string $hash, string $algorithm): bool
{
    $expected = hash($algorithm, $str);
    $actual = strtolower(preg_replace('/^0x/i', '', trim($hash)) ?? '');

    return hash_equals($expected, $actual);
}

function ssh_u32(int $n): string
{
    return pack('N', $n);
}

function ssh_string(string $value): string
{
    return ssh_u32(strlen($value)) . $value;
}

function ssh_mpint(string $bin): string
{
    $bin = ltrim($bin, "\x00");
    if ($bin === '') {
        $bin = "\x00";
    }
    if ((ord($bin[0]) & 0x80) !== 0) {
        $bin = "\x00" . $bin;
    }

    return ssh_string($bin);
}

function ssh_public_line(string $type, string $blob, string $comment): string
{
    $line = $type . ' ' . base64_encode($blob);
    if ($comment !== '') {
        $line .= ' ' . $comment;
    }

    return $line;
}

function ssh_openssh_private(string $publicBlob, string $privateBody, string $comment, string $passphrase = ''): string
{
    $check = random_bytes(4);
    $inner = $check . $check . $privateBody . ssh_string($comment);
    $encrypted = $passphrase !== '';
    $block = $encrypted ? 16 : 8;
    $pad = 0;
    while (strlen($inner) % $block !== 0) {
        $pad++;
        $inner .= chr($pad);
    }

    if (!$encrypted) {
        $payload = 'openssh-key-v1' . "\0"
            . ssh_string('none')
            . ssh_string('none')
            . ssh_string('')
            . pack('N', 1)
            . ssh_string($publicBlob)
            . ssh_string($inner);
    } else {
        if (!function_exists('openssl_encrypt')) {
            fail('OpenSSL is not available on this server', 500, 'ssh');
        }

        try {
            $salt = random_bytes(16);
            $rounds = 16;
            $keys = \App\Support\BcryptPbkdf::derive($passphrase, $salt, 48, $rounds);
        } catch (Throwable) {
            fail('Unable to protect the private key with that passphrase', 500, 'ssh');
        }

        $aesKey = substr($keys, 0, 32);
        $iv = substr($keys, 32, 16);
        $cipher = openssl_encrypt($inner, 'aes-256-ctr', $aesKey, OPENSSL_RAW_DATA, $iv);
        if (!is_string($cipher) || $cipher === '') {
            fail('Unable to protect the private key with that passphrase', 500, 'ssh');
        }

        $payload = 'openssh-key-v1' . "\0"
            . ssh_string('aes256-ctr')
            . ssh_string('bcrypt')
            . ssh_string(ssh_string($salt) . pack('N', $rounds))
            . pack('N', 1)
            . ssh_string($publicBlob)
            . ssh_string($cipher);
    }

    return "-----BEGIN OPENSSH PRIVATE KEY-----\n"
        . chunk_split(base64_encode($payload), 70, "\n")
        . "-----END OPENSSH PRIVATE KEY-----\n";
}

function generate_ssh_ed25519(string $comment, string $passphrase = ''): array
{
    if (!function_exists('sodium_crypto_sign_keypair')) {
        fail('Ed25519 is not available on this server', 500, 'ssh');
    }

    $pair = sodium_crypto_sign_keypair();
    $pk = sodium_crypto_sign_publickey($pair);
    $sk = sodium_crypto_sign_secretkey($pair);
    $pubBlob = ssh_string('ssh-ed25519') . ssh_string($pk);
    $privBody = ssh_string('ssh-ed25519') . ssh_string($pk) . ssh_string($sk);
    $result = [
        'algorithm' => 'ed25519',
        'comment' => $comment,
        'public_key' => ssh_public_line('ssh-ed25519', $pubBlob, $comment),
        'private_key' => ssh_openssh_private($pubBlob, $privBody, $comment, $passphrase),
    ];

    sodium_memzero($pair);
    sodium_memzero($sk);

    return $result;
}

function generate_ssh_rsa(int $bits, string $comment, string $passphrase = ''): array
{
    if (!function_exists('openssl_pkey_new')) {
        fail('OpenSSL is not available on this server', 500, 'ssh');
    }

    $key = openssl_pkey_new([
        'private_key_bits' => $bits,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    if ($key === false) {
        fail('Unable to generate RSA key', 500, 'ssh');
    }

    $details = openssl_pkey_get_details($key);
    if ($details === false || !isset($details['rsa']) || !is_array($details['rsa'])) {
        fail('Unable to export RSA key', 500, 'ssh');
    }

    $rsa = $details['rsa'];
    foreach (['n', 'e', 'd', 'p', 'q', 'iqmp'] as $part) {
        if (!isset($rsa[$part]) || !is_string($rsa[$part]) || $rsa[$part] === '') {
            fail('Unable to export RSA key', 500, 'ssh');
        }
    }

    $pubBlob = ssh_string('ssh-rsa') . ssh_mpint($rsa['e']) . ssh_mpint($rsa['n']);
    $privBody = ssh_string('ssh-rsa')
        . ssh_mpint($rsa['n'])
        . ssh_mpint($rsa['e'])
        . ssh_mpint($rsa['d'])
        . ssh_mpint($rsa['iqmp'])
        . ssh_mpint($rsa['p'])
        . ssh_mpint($rsa['q']);

    return [
        'algorithm' => 'rsa' . $bits,
        'comment' => $comment,
        'public_key' => ssh_public_line('ssh-rsa', $pubBlob, $comment),
        'private_key' => ssh_openssh_private($pubBlob, $privBody, $comment, $passphrase),
    ];
}

function app_ca_file(): ?string
{
    $candidates = [
        (string) ini_get('curl.cainfo'),
        (string) ini_get('openssl.cafile'),
        (string) (getenv('SSL_CERT_FILE') ?: ''),
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/pki/tls/certs/ca-bundle.crt',
        'C:\\Program Files\\Git\\usr\\ssl\\certs\\ca-bundle.crt',
        'C:\\Program Files\\Git\\mingw64\\ssl\\certs\\ca-bundle.crt',
    ];

    foreach ($candidates as $path) {
        if ($path !== '' && is_file($path) && is_readable($path)) {
            return $path;
        }
    }

    return null;
}

function app_http_get(string $url, array $headers = [], int $timeout = 8): array
{
    $ua = 'tools.rajujha.dev/' . app_config()['version'];

    if (function_exists('curl_init')) {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Unable to start request'];
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if (defined('CURLPROTO_HTTPS')) {
            $opts[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }
        if (defined('CURLSSLOPT_NATIVE_CA')) {
            $opts[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
        }
        $caFile = app_ca_file();
        if ($caFile !== null) {
            $opts[CURLOPT_CAINFO] = $caFile;
        }

        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);

        if ($body === false) {
            return ['ok' => false, 'status' => $status, 'body' => '', 'error' => $err !== '' ? $err : 'Request failed'];
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => (string) $body,
            'error' => null,
        ];
    }

    $headerBlock = "User-Agent: {$ua}\r\n";
    foreach ($headers as $name => $value) {
        $headerBlock .= $name . ': ' . $value . "\r\n";
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => $headerBlock,
            'timeout' => $timeout,
            'follow_location' => 0,
            'ignore_errors' => false,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Request failed'];
    }

    return [
        'ok' => true,
        'status' => 200,
        'body' => (string) $body,
        'error' => null,
    ];
}

function dns_type_name(int $type): string
{
    return match ($type) {
        1 => 'A',
        2 => 'NS',
        5 => 'CNAME',
        15 => 'MX',
        16 => 'TXT',
        28 => 'AAAA',
        default => (string) $type,
    };
}

function dns_status_name(int $status): string
{
    return match ($status) {
        0 => 'NOERROR',
        1 => 'FORMERR',
        2 => 'SERVFAIL',
        3 => 'NXDOMAIN',
        4 => 'NOTIMP',
        5 => 'REFUSED',
        default => 'STATUS_' . $status,
    };
}

function valid_dns_host(string $host): bool
{
    if ($host === '' || strlen($host) > 253 || str_contains($host, '://') || str_contains($host, ' ')) {
        return false;
    }

    $normalized = rtrim($host, '.');
    if ($normalized === '') {
        return false;
    }

    if (filter_var($normalized, FILTER_VALIDATE_IP)) {
        return true;
    }

    return (bool) preg_match(
        '/^(?=.{1,253}$)(?:[a-zA-Z0-9_](?:[a-zA-Z0-9_-]{0,61}[a-zA-Z0-9_])?\.)*[a-zA-Z0-9_](?:[a-zA-Z0-9_-]{0,61}[a-zA-Z0-9_])?$/',
        $normalized
    );
}

