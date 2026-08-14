<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
app_no_store_headers();
header('X-Content-Type-Options: nosniff');

const APP_SENSITIVE_TOOLS = ['hash', 'base64', 'encryption'];
const APP_RATE_LIMITED_TOOLS = ['hash', 'encryption', 'password', 'secret', 'uuid'];

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
        || strlen($salt) < 16
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

$tool = request_tool();

if ($tool !== '' && !in_array(request_method(), ['GET', 'POST', 'HEAD'], true)) {
    reject_method($tool, ['GET', 'POST'], 'Method not allowed');
}

require_http_method($tool);

if (in_array($tool, APP_RATE_LIMITED_TOOLS, true)) {
    app_rate_limit_enforce($tool);
}

if ($tool === 'password') {
    $length = filter_var(param('length', '24'), FILTER_VALIDATE_INT);
    $count = filter_var(param('count', '1'), FILTER_VALIDATE_INT);

    if ($length === false || $length < 8 || $length > 128) {
        fail('length must be an integer from 8 to 128', 400, 'password');
    }

    if ($count === false || $count < 1 || $count > 20) {
        fail('count must be an integer from 1 to 20', 400, 'password');
    }

    $upper = boolParam('upper', true);
    $lower = boolParam('lower', true);
    $numbers = boolParam('numbers', true);
    $symbols = boolParam('symbols', true);

    $passwords = [];
    for ($i = 0; $i < $count; $i++) {
        $passwords[] = generatePassword($length, $upper, $lower, $numbers, $symbols);
    }

    ok('password', [
        'length' => $length,
        'count' => $count,
        'upper' => $upper,
        'lower' => $lower,
        'numbers' => $numbers,
        'symbols' => $symbols,
        'password' => $passwords[0],
        'passwords' => $passwords,
    ]);
}

if ($tool === 'hash') {
    $str = requireInput();
    $alg = strtolower(param('algorithm', 'sha256'));

    if ($alg === 'bcrypt') {
        $security = app_security();
        $cost = filter_var(param('cost', (string) $security['bcrypt_cost']), FILTER_VALIDATE_INT);
        if ($cost === false || $cost < APP_BCRYPT_COST_MIN || $cost > $security['max_bcrypt_cost']) {
            fail(
                'bcrypt cost must be an integer from ' . APP_BCRYPT_COST_MIN . ' to ' . $security['max_bcrypt_cost'],
                400,
                'hash'
            );
        }

        $hash = password_hash($str, PASSWORD_BCRYPT, ['cost' => $cost]);
        if ($hash === false) {
            fail('Unable to generate bcrypt hash', 500, 'hash');
        }

        ok('hash', [
            'algorithm' => 'bcrypt',
            'cost' => $cost,
            'salt' => substr($hash, 7, 22),
            'hash' => $hash,
        ]);
    }

    $allowed = ['md5', 'sha1', 'sha256', 'sha384', 'sha512'];

    if ($alg === 'all') {
        $result = [];
        foreach ($allowed as $algorithm) {
            $result[$algorithm] = hash($algorithm, $str);
        }

        ok('hash', [
            'algorithm' => 'all',
            'input_length' => strlen($str),
            'hashes' => $result,
        ]);
    }

    if (!in_array($alg, $allowed, true)) {
        fail('Unsupported algorithm', 400, 'hash');
    }

    ok('hash', [
        'algorithm' => $alg,
        'input_length' => strlen($str),
        'hash' => hash($alg, $str),
    ]);
}

if ($tool === 'timestamp') {
    $raw = param('timestamp', param('ts'));

    if ($raw === '') {
        $now = microtime(true);
        ok('timestamp', [
            'current' => true,
            'timezone' => 'UTC',
            'unix_seconds' => (int) floor($now),
            'unix_milliseconds' => (int) round($now * 1000),
            'iso_8601' => gmdate('c'),
            'utc' => gmdate('Y-m-d H:i:s') . ' UTC',
        ]);
    }

    if (!is_numeric($raw)) {
        fail('timestamp must be numeric', 400, 'timestamp');
    }

    $unit = strtolower(param('unit', 's'));
    if (!in_array($unit, ['s', 'ms'], true)) {
        fail('unit must be s or ms', 400, 'timestamp');
    }

    $sec = $unit === 'ms' ? (float) $raw / 1000 : (float) $raw;
    $whole = (int) floor($sec);
    $fraction = max(0.0, $sec - $whole);

    ok('timestamp', [
        'input' => $raw,
        'unit' => $unit,
        'unix_seconds' => $sec,
        'unix_milliseconds' => (int) round($sec * 1000),
        'iso_8601' => gmdate('Y-m-d\TH:i:s', $whole) . sprintf('.%03dZ', (int) round($fraction * 1000)),
        'utc' => gmdate('Y-m-d H:i:s', $whole) . ' UTC',
    ]);
}

if ($tool === 'uuid') {
    $count = filter_var(param('count', '1'), FILTER_VALIDATE_INT);
    if ($count === false || $count < 1 || $count > 100) {
        fail('count must be an integer from 1 to 100', 400, 'uuid');
    }

    $uuids = [];
    for ($i = 0; $i < $count; $i++) {
        $uuids[] = generateUuidV4();
    }

    ok('uuid', [
        'version' => 4,
        'count' => $count,
        'uuid' => $uuids[0],
        'uuids' => $uuids,
    ]);
}

if ($tool === 'secret') {
    $length = filter_var(param('length', '48'), FILTER_VALIDATE_INT);
    $count = filter_var(param('count', '1'), FILTER_VALIDATE_INT);
    $format = strtolower(param('format', 'hex'));

    if ($length === false || $length < 16 || $length > 256) {
        fail('length must be an integer from 16 to 256', 400, 'secret');
    }

    if ($count === false || $count < 1 || $count > 20) {
        fail('count must be an integer from 1 to 20', 400, 'secret');
    }

    if (!in_array($format, ['hex', 'base64', 'base64url'], true)) {
        fail('format must be hex, base64, or base64url', 400, 'secret');
    }

    $secrets = [];
    for ($i = 0; $i < $count; $i++) {
        $secrets[] = generateSecret($length, $format);
    }

    ok('secret', [
        'length' => $length,
        'format' => $format,
        'count' => $count,
        'secret' => $secrets[0],
        'secrets' => $secrets,
    ]);
}

if ($tool === 'base64') {
    $str = requireInput();
    $mode = strtolower(param('mode', 'encode'));

    if ($mode === 'decode') {
        $decoded = base64_decode($str, true);
        if ($decoded === false) {
            fail('Invalid Base64', 400, 'base64');
        }

        ok('base64', [
            'mode' => 'decode',
            'output' => $decoded,
        ]);
    }

    if ($mode !== 'encode') {
        fail('mode must be encode or decode', 400, 'base64');
    }

    ok('base64', [
        'mode' => 'encode',
        'output' => base64_encode($str),
    ]);
}

if ($tool === 'user-agent') {
    $ua = param('ua', param('user_agent', $_SERVER['HTTP_USER_AGENT'] ?? ''));
    assert_size($ua, 'ua');

    if ($ua === '') {
        fail('Missing User-Agent. Pass ua=... or call from a browser.', 400, 'user-agent', 'MISSING_PARAMETER');
    }

    ok('user-agent', parseUserAgent($ua));
}

if ($tool === 'ip') {
    $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $family = $remote !== '' ? classifyIp($remote) : null;
    $ipv4 = $family === 'ipv4' ? $remote : null;
    $ipv6 = $family === 'ipv6' ? $remote : null;
    $xRealIp = trim((string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''));
    $xForwardedFor = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));

    ok('ip', [
        'ip' => $remote !== '' ? $remote : null,
        'version' => $family === 'ipv4' ? 4 : ($family === 'ipv6' ? 6 : null),
        'ipv4' => $ipv4,
        'ipv6' => $ipv6,
        'ipv4_status' => $ipv4 !== null ? 'detected' : 'not_detected',
        'ipv6_status' => $ipv6 !== null ? 'detected' : 'not_detected',
        'proxy_headers' => [
            'trusted' => false,
            'x_real_ip' => $xRealIp !== '' ? $xRealIp : null,
            'x_forwarded_for' => $xForwardedFor !== '' ? $xForwardedFor : null,
        ],
        'note' => 'ipv4/ipv6 reflect REMOTE_ADDR for this TCP connection only. A connection is one address family, so the other family is not detected here. X-Forwarded-For and X-Real-IP are listed for inspection and are not trusted.',
    ]);
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

if ($tool === 'encryption') {
    if (!function_exists('openssl_encrypt')) {
        fail('OpenSSL is not available on this server', 500, 'encryption');
    }

    $str = inputValue();
    $key = param('key');
    $mode = strtolower(param('mode', 'encrypt'));

    assert_size($str, 'str');
    assert_size($key, 'key');

    if ($key === '') {
        fail('Missing parameter: key', 400, 'encryption', 'MISSING_PARAMETER');
    }

    if ($mode === 'encrypt') {
        $security = app_security();
        $iterations = $security['encryption_iterations'];
        $requested = param('iter', param('iterations'));

        if ($requested !== '') {
            $parsed = filter_var($requested, FILTER_VALIDATE_INT);
            if (
                $parsed === false
                || $parsed < APP_PBKDF2_ITER_MIN
                || $parsed > $security['max_encryption_iterations']
            ) {
                fail(
                    'iter must be an integer from ' . APP_PBKDF2_ITER_MIN . ' to ' . $security['max_encryption_iterations'],
                    400,
                    'encryption'
                );
            }
            $iterations = $parsed;
        }

        $version = encryption_request_version();
        $payload = encrypt_payload($str, $key, $iterations, $version);
        $compact = encrypt_compact_encode($payload);

        ok('encryption', [
            'mode' => 'encrypt',
            'version' => $version,
            'compact' => $compact,
            'json' => $payload,
        ]);
    }

    if ($mode === 'decrypt') {
        if ($str === '') {
            fail('Missing parameter: str', 400, 'encryption', 'MISSING_PARAMETER');
        }

        $plain = decrypt_payload($str, $key);

        ok('encryption', [
            'mode' => 'decrypt',
            'output' => $plain,
        ]);
    }

    fail('mode must be encrypt or decrypt', 400, 'encryption');
}

fail('Unknown tool.', 404);
