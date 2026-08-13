<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

function out(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );
    exit;
}

function request_params(): array
{
    static $params = null;

    if ($params !== null) {
        return $params;
    }

    $params = $_GET;

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            if (is_array($decoded)) {
                $params = array_merge($params, $decoded);
            }
        } else {
            $params = array_merge($params, $_POST);
        }
    }

    return $params;
}

function param(string $key, string $fallback = ''): string
{
    $params = request_params();

    return isset($params[$key]) ? (string) $params[$key] : $fallback;
}

function inputValue(): string
{
    return param('str', param('string', param('input')));
}

function requireInput(): string
{
    $params = request_params();

    if (!isset($params['str']) && !isset($params['string']) && !isset($params['input'])) {
        out(['ok' => false, 'error' => 'Missing parameter: str'], 400);
    }

    return inputValue();
}

function boolParam(string $key, bool $default = true): bool
{
    $params = request_params();

    if (!isset($params[$key])) {
        return $default;
    }

    $value = strtolower(trim((string) $params[$key]));

    if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    out(['ok' => false, 'error' => "Parameter {$key} must be a boolean"], 400);
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
        out(['ok' => false, 'error' => 'Select at least one character set'], 400);
    }

    $alphabet = implode('', $sets);
    $alphabetLength = strlen($alphabet);
    $password = '';

    // Guarantee at least one character from each selected set when length allows.
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

$tool = strtolower(param('tool'));

if ($tool === 'password') {
    $length = filter_var(param('length', '24'), FILTER_VALIDATE_INT);
    $count = filter_var(param('count', '1'), FILTER_VALIDATE_INT);

    if ($length === false || $length < 8 || $length > 128) {
        out(['ok' => false, 'error' => 'length must be an integer from 8 to 128'], 400);
    }

    if ($count === false || $count < 1 || $count > 20) {
        out(['ok' => false, 'error' => 'count must be an integer from 1 to 20'], 400);
    }

    $upper = boolParam('upper', true);
    $lower = boolParam('lower', true);
    $numbers = boolParam('numbers', true);
    $symbols = boolParam('symbols', true);

    $passwords = [];
    for ($i = 0; $i < $count; $i++) {
        $passwords[] = generatePassword($length, $upper, $lower, $numbers, $symbols);
    }

    out([
        'ok' => true,
        'tool' => 'password',
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
        $cost = filter_var(param('cost', '12'), FILTER_VALIDATE_INT);
        if ($cost === false || $cost < 4 || $cost > 31) {
            out(['ok' => false, 'error' => 'bcrypt cost must be an integer from 4 to 31'], 400);
        }

        $hash = password_hash($str, PASSWORD_BCRYPT, ['cost' => $cost]);
        if ($hash === false) {
            out(['ok' => false, 'error' => 'Unable to generate bcrypt hash'], 500);
        }

        out([
            'ok' => true,
            'tool' => 'hash',
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

        out([
            'ok' => true,
            'tool' => 'hash',
            'algorithm' => 'all',
            'input_length' => strlen($str),
            'hashes' => $result,
        ]);
    }

    if (!in_array($alg, $allowed, true)) {
        out(['ok' => false, 'error' => 'Unsupported algorithm'], 400);
    }

    out([
        'ok' => true,
        'tool' => 'hash',
        'algorithm' => $alg,
        'input_length' => strlen($str),
        'hash' => hash($alg, $str),
    ]);
}

if ($tool === 'timestamp') {
    $raw = param('timestamp', param('ts'));

    if ($raw === '') {
        $now = microtime(true);
        out([
            'ok' => true,
            'tool' => 'timestamp',
            'current' => true,
            'timezone' => 'UTC',
            'unix_seconds' => (int) floor($now),
            'unix_milliseconds' => (int) round($now * 1000),
            'iso_8601' => gmdate('c'),
            'utc' => gmdate('Y-m-d H:i:s') . ' UTC',
        ]);
    }

    if (!is_numeric($raw)) {
        out(['ok' => false, 'error' => 'timestamp must be numeric'], 400);
    }

    $unit = strtolower(param('unit', 's'));
    if (!in_array($unit, ['s', 'ms'], true)) {
        out(['ok' => false, 'error' => 'unit must be s or ms'], 400);
    }

    $sec = $unit === 'ms' ? (float) $raw / 1000 : (float) $raw;
    $whole = (int) floor($sec);
    $fraction = max(0.0, $sec - $whole);

    out([
        'ok' => true,
        'tool' => 'timestamp',
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
        out(['ok' => false, 'error' => 'count must be an integer from 1 to 100'], 400);
    }

    $uuids = [];
    for ($i = 0; $i < $count; $i++) {
        $uuids[] = generateUuidV4();
    }

    out([
        'ok' => true,
        'tool' => 'uuid',
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
        out(['ok' => false, 'error' => 'length must be an integer from 16 to 256'], 400);
    }

    if ($count === false || $count < 1 || $count > 20) {
        out(['ok' => false, 'error' => 'count must be an integer from 1 to 20'], 400);
    }

    if (!in_array($format, ['hex', 'base64', 'base64url'], true)) {
        out(['ok' => false, 'error' => 'format must be hex, base64, or base64url'], 400);
    }

    $secrets = [];
    for ($i = 0; $i < $count; $i++) {
        $secrets[] = generateSecret($length, $format);
    }

    out([
        'ok' => true,
        'tool' => 'secret',
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
            out(['ok' => false, 'error' => 'Invalid Base64'], 400);
        }

        out([
            'ok' => true,
            'tool' => 'base64',
            'mode' => 'decode',
            'output' => $decoded,
        ]);
    }

    if ($mode !== 'encode') {
        out(['ok' => false, 'error' => 'mode must be encode or decode'], 400);
    }

    out([
        'ok' => true,
        'tool' => 'base64',
        'mode' => 'encode',
        'output' => base64_encode($str),
    ]);
}

if ($tool === 'user-agent') {
    $ua = param('ua', param('user_agent', $_SERVER['HTTP_USER_AGENT'] ?? ''));

    if ($ua === '') {
        out(['ok' => false, 'error' => 'Missing User-Agent. Pass ua=... or call from a browser.'], 400);
    }

    out([
        'ok' => true,
        'tool' => 'user-agent',
        ...parseUserAgent($ua),
    ]);
}

if ($tool === 'ip') {
    $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $family = $remote !== '' ? classifyIp($remote) : null;

    $ipv4 = $family === 'ipv4' ? $remote : null;
    $ipv6 = $family === 'ipv6' ? $remote : null;

    out([
        'ok' => true,
        'tool' => 'ip',
        'ip' => $remote,
        'version' => $family === 'ipv4' ? 4 : ($family === 'ipv6' ? 6 : null),
        'ipv4' => $ipv4,
        'ipv6' => $ipv6,
        'remote_addr' => $remote,
        'x_real_ip' => (string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''),
        'x_forwarded_for' => (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
        'note' => 'ipv4/ipv6 reflect the server-observed REMOTE_ADDR for this connection. Proxy headers are exposed separately and are not trusted automatically.',
    ]);
}

if ($tool === 'encryption') {
    $str = inputValue();
    $key = param('key', 'change-this-demo-secret');
    $mode = strtolower(param('mode', 'encrypt'));

    if ($key === '') {
        out(['ok' => false, 'error' => 'Missing parameter: key'], 400);
    }

    if (!function_exists('openssl_encrypt')) {
        out(['ok' => false, 'error' => 'OpenSSL is not available on this server'], 500);
    }

    $method = 'aes-256-gcm';
    $salt = random_bytes(16);
    $iv = random_bytes(12);
    $derived = hash_pbkdf2('sha256', $key, $salt, 120000, 32, true);

    if ($mode === 'encrypt') {
        $tag = '';
        $cipher = openssl_encrypt($str, $method, $derived, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            out(['ok' => false, 'error' => 'Encryption failed'], 500);
        }

        out([
            'ok' => true,
            'tool' => 'encryption',
            'mode' => 'encrypt',
            'algorithm' => 'AES-256-GCM',
            'output' => base64_encode($salt . $iv . $tag . $cipher),
        ]);
    }

    if ($mode === 'decrypt') {
        if ($str === '') {
            out(['ok' => false, 'error' => 'Missing parameter: str'], 400);
        }

        $raw = base64_decode($str, true);
        if ($raw === false || strlen($raw) < 44) {
            out(['ok' => false, 'error' => 'Invalid encrypted payload'], 400);
        }

        $salt = substr($raw, 0, 16);
        $iv = substr($raw, 16, 12);
        $tag = substr($raw, 28, 16);
        $cipher = substr($raw, 44);
        $derived = hash_pbkdf2('sha256', $key, $salt, 120000, 32, true);
        $plain = openssl_decrypt($cipher, $method, $derived, OPENSSL_RAW_DATA, $iv, $tag);

        if ($plain === false) {
            out(['ok' => false, 'error' => 'Decryption failed. Check the secret key and encrypted value.'], 400);
        }

        out([
            'ok' => true,
            'tool' => 'encryption',
            'mode' => 'decrypt',
            'algorithm' => 'AES-256-GCM',
            'output' => $plain,
        ]);
    }

    out(['ok' => false, 'error' => 'mode must be encrypt or decrypt'], 400);
}

out(['ok' => false, 'error' => 'Unknown tool.'], 404);
