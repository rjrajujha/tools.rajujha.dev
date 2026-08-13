<?php
declare(strict_types=1);

/**
 * HTTP security regression tests. Requires the app server:
 *
 *   php -S 127.0.0.1:8080 router.php
 *   php tests/http.php
 *
 * Optional: BASE_URL=http://127.0.0.1:8080
 */

$base = rtrim(getenv('BASE_URL') ?: 'http://127.0.0.1:8080', '/');
$failed = 0;
$passed = 0;

function http_request(
    string $method,
    string $url,
    ?string $body = null,
    array $headers = []
): array {
    $headerLines = [];
    foreach ($headers as $name => $value) {
        $headerLines[] = $name . ': ' . $value;
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headerLines),
            'content' => $body ?? '',
            'ignore_errors' => true,
            'timeout' => 20,
            'follow_location' => 0,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if (function_exists('http_get_last_response_headers')) {
        $rawHeaders = http_get_last_response_headers() ?: [];
    } else {
        $rawHeaders = $http_response_header ?? [];
    }

    $status = 0;
    $headerMap = [];
    foreach ($rawHeaders as $line) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $line, $match)) {
            $status = (int) $match[1];
            continue;
        }
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            $headerMap[strtolower(trim($parts[0]))] = trim($parts[1]);
        }
    }

    $json = null;
    if (is_string($response) && $response !== '') {
        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            $json = $decoded;
        }
    }

    return [
        'status' => $status,
        'body' => is_string($response) ? $response : '',
        'json' => $json,
        'headers' => $headerMap,
    ];
}

function assert_true(bool $ok, string $label): void
{
    global $failed, $passed;
    if ($ok) {
        $passed++;
        echo "PASS — {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL — {$label}\n";
}

function header_has(array $headers, string $name, string $needle): bool
{
    $value = strtolower((string) ($headers[$name] ?? ''));
    return str_contains($value, strtolower($needle));
}

function reset_rate_limit_files(): void
{
    $dir = getenv('APP_RATE_LIMIT_DIR');
    if (!is_string($dir) || $dir === '' || str_contains($dir, '..')) {
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'rate-limit';
    }
    if (!is_dir($dir)) {
        return;
    }
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
        if (preg_match('/[a-f0-9]{64}\\.json$/', $file)) {
            @unlink($file);
        }
    }
}

reset_rate_limit_files();

$home = http_request('GET', $base . '/');
assert_true($home['status'] === 200 && str_contains($home['body'], 'Developer tools'), 'GET /');
assert_true(str_contains($home['body'], 'Encrypt-Decrypt'), 'home lists Encrypt-Decrypt');
assert_true(!str_contains($home['body'], 'Encrypt - Decrypt') && !str_contains($home['body'], 'Encrypt / Decrypt'), 'home does not use old encryption names');

$encPage = http_request('GET', $base . '/encryption');
assert_true($encPage['status'] === 200 && str_contains($encPage['body'], 'Encrypt-Decrypt'), 'tool name is Encrypt-Decrypt');
assert_true(
    (bool) preg_match('/<select[^>]*id="mode"[^>]*>[\s\S]*?<option value="encrypt" selected>/', $encPage['body']),
    'default mode is Encrypt'
);
assert_true(
    (bool) preg_match('/<select[^>]*id="mode"[^>]*>[\s\S]*?<option value="decrypt">/', $encPage['body']),
    'Decrypt is selectable in the mode dropdown'
);
assert_true(!str_contains($encPage['body'], 'Encrypt-V1'), 'Encrypt-V1 is not visible in the UI');
assert_true(!str_contains($encPage['body'], 'value="encrypt-v1"'), 'Encrypt-V1 is not a mode option');
assert_true(str_contains($encPage['body'], 'Compact Encrypted Text'), 'Encrypt UI includes compact output card');
assert_true(str_contains($encPage['body'], 'Encrypted JSON / Object'), 'Encrypt UI includes JSON output card');
assert_true(substr_count($encPage['body'], '>Copy</button>') >= 3, 'Encrypt/Decrypt copy buttons are labeled Copy');
assert_true(!str_contains($encPage['body'], 'Copy encrypted'), 'long Copy labels are not used');
assert_true(!str_contains($encPage['body'], 'Copy decrypted'), 'Decrypt copy button is labeled Copy');

$health = http_request('GET', $base . '/health');
assert_true($health['status'] === 200 && ($health['json']['status'] ?? null) === 'ok', 'GET /health');
assert_true(header_has($health['headers'], 'cache-control', 'no-store'), '/health is not cacheable');

$missing = http_request('GET', $base . '/no-such-tool');
assert_true($missing['status'] === 404, 'unknown route is 404');

$config = http_request('GET', $base . '/config.json');
assert_true(in_array($config['status'], [403, 404], true), 'config.json is not web-accessible');

$bootstrap = http_request('GET', $base . '/bootstrap.php');
assert_true(in_array($bootstrap['status'], [403, 404], true), 'bootstrap.php is not web-accessible');

$varDir = http_request('GET', $base . '/var/rate-limit/');
assert_true(in_array($varDir['status'], [403, 404], true), 'var/ is not web-accessible');

$uuid = http_request('GET', $base . '/api/uuid');
assert_true($uuid['status'] === 200 && ($uuid['json']['ok'] ?? null) === true, 'GET /api/uuid');
assert_true(header_has($uuid['headers'], 'cache-control', 'no-store'), 'GET API responses are not cacheable');
assert_true(header_has($uuid['headers'], 'x-content-type-options', 'nosniff'), 'API sends nosniff');

$ip = http_request('GET', $base . '/api/ip', null, [
    'X-Forwarded-For' => '8.8.8.8',
    'X-Real-IP' => '1.1.1.1',
]);
assert_true($ip['status'] === 200, 'GET /api/ip');
assert_true(($ip['json']['proxy_headers']['trusted'] ?? null) === false, 'proxy headers are not trusted');
assert_true(($ip['json']['ip'] ?? null) !== '8.8.8.8', 'X-Forwarded-For is not used as client IP');

$hashGet = http_request('GET', $base . '/api/hash?str=secret');
assert_true($hashGet['status'] === 405, 'GET /api/hash is 405');
assert_true(header_has($hashGet['headers'], 'allow', 'POST'), 'GET /api/hash Allow: POST');

$putPassword = http_request('PUT', $base . '/api/password');
assert_true($putPassword['status'] === 405, 'PUT /api/password is 405');

$badJson = http_request('POST', $base . '/api/hash', '{', [
    'Content-Type' => 'application/json',
]);
assert_true($badJson['status'] === 400 && ($badJson['json']['ok'] ?? null) === false, 'malformed JSON is 400');

$jsonList = http_request('POST', $base . '/api/hash', '["x"]', [
    'Content-Type' => 'application/json',
]);
assert_true($jsonList['status'] === 400, 'JSON array body is rejected');

$arrayParam = http_request('POST', $base . '/api/hash', json_encode(['str' => ['x'], 'algorithm' => 'md5']), [
    'Content-Type' => 'application/json',
]);
assert_true($arrayParam['status'] === 400, 'array where string expected is rejected');

$objectParam = http_request('POST', $base . '/api/hash', json_encode(['str' => ['nested' => 'x'], 'algorithm' => 'md5']), [
    'Content-Type' => 'application/json',
]);
assert_true($objectParam['status'] === 400, 'object where string expected is rejected');

$floatCost = http_request('POST', $base . '/api/hash', json_encode(['str' => 'x', 'algorithm' => 'bcrypt', 'cost' => 12.5]), [
    'Content-Type' => 'application/json',
]);
assert_true($floatCost['status'] === 400, 'float bcrypt cost is rejected');

$highCost = http_request('POST', $base . '/api/hash', json_encode(['str' => 'x', 'algorithm' => 'bcrypt', 'cost' => 31]), [
    'Content-Type' => 'application/json',
]);
assert_true($highCost['status'] === 400, 'bcrypt cost above config max is rejected');

$negLen = http_request('GET', $base . '/api/password?length=-1');
assert_true($negLen['status'] === 400, 'negative password length is rejected');

$bigInt = http_request('GET', $base . '/api/uuid?count=999999999999');
assert_true($bigInt['status'] === 400, 'oversized uuid count is rejected');

$crlf = http_request('GET', $base . "/api/user-agent?ua=" . rawurlencode("ok\r\nX-Injected: 1"));
assert_true($crlf['status'] === 200, 'CRLF in ua does not break the response');
assert_true(!isset($crlf['headers']['x-injected']), 'CRLF does not inject a response header');

$xss = http_request('POST', $base . '/api/hash', json_encode([
    'str' => '<script>alert(1)</script>',
    'algorithm' => 'md5',
]), ['Content-Type' => 'application/json']);
assert_true($xss['status'] === 200, 'XSS payload is accepted as hash input');
assert_true(!str_contains($xss['body'], '<script>'), 'JSON response does not echo raw HTML');

$nullByte = http_request('POST', $base . '/api/hash', json_encode([
    'str' => "abc\0def",
    'algorithm' => 'md5',
]), ['Content-Type' => 'application/json']);
assert_true($nullByte['status'] === 400, 'null bytes in parameters are rejected');

$wrongType = http_request('POST', $base . '/api/hash', 'str=hello&algorithm=md5', [
    'Content-Type' => 'text/plain',
]);
assert_true($wrongType['status'] === 415, 'unsupported Content-Type is 415');

$oversize = str_repeat('a', 70000);
$bigBody = http_request('POST', $base . '/api/hash', json_encode(['str' => $oversize, 'algorithm' => 'md5']), [
    'Content-Type' => 'application/json',
]);
assert_true(in_array($bigBody['status'], [400, 413], true), 'oversized hash input is rejected');

$hashOk = http_request('POST', $base . '/api/hash', json_encode(['str' => 'admin123', 'algorithm' => 'sha256']), [
    'Content-Type' => 'application/json',
]);
assert_true($hashOk['status'] === 200 && isset($hashOk['json']['hash']), 'POST /api/hash sha256');
assert_true(header_has($hashOk['headers'], 'cache-control', 'no-store'), 'POST /api/hash is not cacheable');

$b64 = http_request('POST', $base . '/api/base64', json_encode(['str' => 'hello', 'mode' => 'encode']), [
    'Content-Type' => 'application/json',
]);
assert_true($b64['status'] === 200 && ($b64['json']['output'] ?? null) === base64_encode('hello'), 'POST /api/base64');

reset_rate_limit_files();

$enc = http_request('POST', $base . '/api/encryption', json_encode([
    'str' => 'hello',
    'key' => 'unit-test-key',
    'mode' => 'encrypt',
]), ['Content-Type' => 'application/json']);

$opensslOk = $enc['status'] === 200 && is_array($enc['json']['payload'] ?? null);
assert_true($opensslOk || str_contains((string) ($enc['json']['error'] ?? ''), 'OpenSSL'), 'POST /api/encryption encrypt or OpenSSL unavailable');

if ($opensslOk) {
    $payload = $enc['json']['payload'];
    $compact = (string) ($enc['json']['compact'] ?? '');
    assert_true(($payload['v'] ?? null) === 2, 'encryption payload version is 2');
    assert_true(($enc['json']['version'] ?? null) === 2, 'encrypt response reports version 2');
    assert_true(($payload['alg'] ?? null) === 'AES-256-GCM', 'encryption algorithm is AES-256-GCM');
    assert_true($compact !== '' && (bool) preg_match('#^[A-Za-z0-9+/]+=*$#', $compact), 'encrypt returns compact Base64');
    assert_true(is_array($enc['json']['json'] ?? null), 'encrypt returns json object alias');
    assert_true(($enc['json']['json']['salt'] ?? null) === ($payload['salt'] ?? null), 'json alias matches payload from one encryption');

    $defaultV = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => 'hello',
        'key' => 'unit-test-key',
        'mode' => 'encrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true(($defaultV['json']['version'] ?? null) === 2 && (($defaultV['json']['payload']['v'] ?? null) === 2), 'omitted v defaults to 2');

    $explicitV2 = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => 'hello',
        'key' => 'unit-test-key',
        'mode' => 'encrypt',
        'v' => 2,
    ]), ['Content-Type' => 'application/json']);
    assert_true(($explicitV2['json']['version'] ?? null) === 2 && (($explicitV2['json']['payload']['v'] ?? null) === 2), 'v=2 uses V2 encryption');

    foreach ([3, 99, 'abc'] as $badV) {
        $bad = http_request('POST', $base . '/api/encryption', json_encode([
            'str' => 'hello',
            'key' => 'unit-test-key',
            'mode' => 'encrypt',
            'v' => $badV,
        ]), ['Content-Type' => 'application/json']);
        assert_true($bad['status'] === 400, 'unsupported v=' . json_encode($badV) . ' is rejected');
    }

    $legacyMode = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => 'hello',
        'key' => 'unit-test-key',
        'mode' => 'encrypt-v1',
    ]), ['Content-Type' => 'application/json']);
    assert_true($legacyMode['status'] === 400, 'mode=encrypt-v1 is rejected');

    reset_rate_limit_files();

    $round = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => $enc['json']['output'],
        'key' => 'unit-test-key',
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true($round['status'] === 200 && ($round['json']['output'] ?? null) === 'hello', 'encryption round-trip');

    $compactRound = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => $compact,
        'key' => 'unit-test-key',
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true(
        $compactRound['status'] === 200 && ($compactRound['json']['output'] ?? null) === 'hello',
        'compact Base64 decrypts to the same plaintext'
    );

    reset_rate_limit_files();

    $strong = bin2hex(random_bytes(24));
    $strongEnc = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => "hello 🔐 café\n{\"a\":1}",
        'key' => $strong,
        'mode' => 'encrypt',
    ]), ['Content-Type' => 'application/json']);
    $strongCompact = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => $strongEnc['json']['compact'] ?? '',
        'key' => $strong,
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    $strongJson = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => $strongEnc['json']['output'] ?? '',
        'key' => $strong,
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true(
        $strongEnc['status'] === 200
        && ($strongCompact['json']['output'] ?? null) === "hello 🔐 café\n{\"a\":1}"
        && ($strongJson['json']['output'] ?? null) === "hello 🔐 café\n{\"a\":1}",
        '48-char hex secret round-trips compact and JSON to the same plaintext'
    );

    $wrong = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => $enc['json']['output'],
        'key' => 'wrong-key',
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true($wrong['status'] === 400, 'wrong encryption key fails closed');

    $wrongCompact = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => $compact,
        'key' => 'wrong-key',
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true($wrongCompact['status'] === 400, 'wrong key fails closed for compact input');

    reset_rate_limit_files();

    $badB64 = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => '%%%not-base64%%%',
        'key' => 'unit-test-key',
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true($badB64['status'] === 400, 'malformed Base64 decrypt fails closed');

    $tampered = $payload;
    $tampered['ct'] = base64_encode((string) base64_decode((string) $tampered['ct'], true) . 'x');
    $tamper = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => json_encode($tampered),
        'key' => 'unit-test-key',
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true($tamper['status'] === 400, 'tampered ciphertext fails closed');

    $aad = $payload;
    $aad['v'] = 1;
    $aadHit = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => json_encode($aad),
        'key' => 'unit-test-key',
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true($aadHit['status'] === 400, 'v2 payload with v1 AAD handling fails closed');

    $badVer = $payload;
    $badVer['v'] = 99;
    $ver = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => json_encode($badVer),
        'key' => 'unit-test-key',
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true($ver['status'] === 400, 'unsupported encryption version is rejected');

    reset_rate_limit_files();

    $highIter = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => 'hello',
        'key' => 'unit-test-key',
        'mode' => 'encrypt',
        'iter' => 600000,
    ]), ['Content-Type' => 'application/json']);
    assert_true($highIter['status'] === 400, 'encryption iter above config max is rejected');

    $saltHit = $payload;
    $saltHit['salt'] = base64_encode(random_bytes(16));
    $saltTamper = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => json_encode($saltHit),
        'key' => 'unit-test-key',
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true($saltTamper['status'] === 400, 'modified salt fails closed');

    $ivHit = $payload;
    $ivHit['iv'] = base64_encode(random_bytes(12));
    $ivTamper = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => json_encode($ivHit),
        'key' => 'unit-test-key',
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true($ivTamper['status'] === 400, 'modified IV fails closed');

    $tagHit = $payload;
    $tagBytes = (string) base64_decode((string) $tagHit['tag'], true);
    $tagBytes[0] = $tagBytes[0] === "\x00" ? "\x01" : "\x00";
    $tagHit['tag'] = base64_encode($tagBytes);
    $tagTamper = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => json_encode($tagHit),
        'key' => 'unit-test-key',
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true($tagTamper['status'] === 400, 'modified authentication tag fails closed');

    $badMode = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => 'hello',
        'key' => 'unit-test-key',
        'mode' => 'encrypt-v2',
    ]), ['Content-Type' => 'application/json']);
    assert_true($badMode['status'] === 400, 'unknown encryption mode is rejected');

    reset_rate_limit_files();

    $v1 = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => "hello 🔐 café\n{\"a\":1}",
        'key' => 'unit-test-key',
        'mode' => 'encrypt',
        'v' => 1,
    ]), ['Content-Type' => 'application/json']);
    assert_true($v1['status'] === 200 && (($v1['json']['payload']['v'] ?? null) === 1), 'v=1 writes payload version 1');
    assert_true(($v1['json']['version'] ?? null) === 1, 'v=1 response reports version 1');
    assert_true(is_string($v1['json']['compact'] ?? null) && ($v1['json']['compact'] ?? '') !== '', 'v=1 also returns compact');

    $v1Round = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => $v1['json']['output'] ?? '',
        'key' => 'unit-test-key',
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true(
        $v1Round['status'] === 200 && ($v1Round['json']['output'] ?? null) === "hello 🔐 café\n{\"a\":1}",
        'decrypt auto-detects V1 JSON payload'
    );

    $v1CompactRound = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => $v1['json']['compact'] ?? '',
        'key' => 'unit-test-key',
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true(
        $v1CompactRound['status'] === 200 && ($v1CompactRound['json']['output'] ?? null) === "hello 🔐 café\n{\"a\":1}",
        'decrypt auto-detects V1 compact payload'
    );

    $again = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => 'hello',
        'key' => 'unit-test-key',
        'mode' => 'encrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true(
        $again['status'] === 200
        && ($again['json']['payload']['ct'] ?? null) !== ($payload['ct'] ?? null)
        && ($again['json']['payload']['salt'] ?? null) !== ($payload['salt'] ?? null)
        && ($again['json']['compact'] ?? null) !== $compact,
        'repeated encrypt produces different ciphertext and salt'
    );

    $empty = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => '',
        'key' => 'unit-test-key',
        'mode' => 'encrypt',
    ]), ['Content-Type' => 'application/json']);
    $emptyRound = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => $empty['json']['compact'] ?? '',
        'key' => 'unit-test-key',
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true($empty['status'] === 200 && $emptyRound['status'] === 200 && ($emptyRound['json']['output'] ?? null) === '', 'empty plaintext round-trips');

    $v1High = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => 'hello',
        'key' => 'unit-test-key',
        'mode' => 'encrypt',
        'v' => 1,
        'iter' => 600000,
    ]), ['Content-Type' => 'application/json']);
    assert_true($v1High['status'] === 400, 'v=1 still rejects iter above config max');
} else {
    echo "SKIP — encryption tamper tests (OpenSSL unavailable on this PHP build)\n";
}

reset_rate_limit_files();

$rateStatuses = [];
for ($i = 0; $i < 25; $i++) {
    $hit = http_request('GET', $base . '/api/uuid?count=1');
    $rateStatuses[] = $hit['status'];
}
$got429 = in_array(429, $rateStatuses, true);
$okCount = count(array_filter($rateStatuses, static fn(int $status): bool => $status === 200));
assert_true($got429, 'rate limiting returns 429 after the window is exceeded');
assert_true($okCount <= 20, 'rate limiting allows at most 20 expensive requests per window');

$retry = null;
foreach (range(0, 24) as $i) {
    if (($rateStatuses[$i] ?? 0) === 429) {
        $retry = http_request('GET', $base . '/api/uuid?count=1');
        break;
    }
}
if ($retry !== null) {
    assert_true(isset($retry['headers']['retry-after']), '429 includes Retry-After');
    assert_true(header_has($retry['headers'], 'cache-control', 'no-store'), '429 is not cacheable');
}

$ts = http_request('GET', $base . '/api/timestamp');
assert_true($ts['status'] === 200, 'cheap GET /api/timestamp remains available');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
