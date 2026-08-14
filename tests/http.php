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

/**
 * @param array<string, mixed>|null $json
 */
function assert_api_envelope(?array $json, string $label, ?bool $expectOk = null): void
{
    assert_true(is_array($json), $label . ' returns JSON');
    if (!is_array($json)) {
        return;
    }

    $keys = array_keys($json);
    sort($keys);
    assert_true($keys === ['data', 'error', 'ok', 'tool'], $label . ' envelope keys are exactly ok/tool/data/error');
    assert_true(is_bool($json['ok']), $label . ' ok is boolean');
    assert_true($json['tool'] === null || is_string($json['tool']), $label . ' tool is string|null');

    if ($expectOk === true) {
        assert_true($json['ok'] === true, $label . ' ok is true');
        assert_true(is_array($json['data']), $label . ' data is object/array');
        assert_true($json['error'] === null, $label . ' error is null');
    } elseif ($expectOk === false) {
        assert_true($json['ok'] === false, $label . ' ok is false');
        assert_true($json['data'] === null || is_array($json['data']), $label . ' data is null or object');
        $error = $json['error'] ?? null;
        assert_true(is_array($error), $label . ' error is an object');
        if (is_array($error)) {
            $errorKeys = array_keys($error);
            sort($errorKeys);
            assert_true($errorKeys === ['code', 'message'], $label . ' error keys are exactly code/message');
            assert_true(is_string($error['code']) && $error['code'] !== '' && (bool) preg_match('/^[A-Z][A-Z0-9_]*$/', $error['code']), $label . ' error.code is a stable code');
            assert_true(is_string($error['message']) && $error['message'] !== '', $label . ' error.message is a non-empty string');
        }
    }
}

/**
 * @param array<string, mixed>|null $json
 */
function api_error_code(?array $json): string
{
    return is_array($json['error'] ?? null) ? (string) ($json['error']['code'] ?? '') : '';
}

/**
 * @param array<string, mixed>|null $json
 */
function api_error_message(?array $json): string
{
    return is_array($json['error'] ?? null) ? (string) ($json['error']['message'] ?? '') : '';
}

/**
 * @param array<string, mixed>|null $json
 * @return array<string, mixed>
 */
function api_data(?array $json): array
{
    return is_array($json['data'] ?? null) ? $json['data'] : [];
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
        if (!preg_match('/[a-f0-9]{64}\\.json$/', $file)) {
            continue;
        }
        for ($i = 0; $i < 5; $i++) {
            if (@unlink($file) || !is_file($file)) {
                break;
            }
            usleep(20000);
        }
        if (is_file($file)) {
            @file_put_contents($file, '{"w":0,"c":0}');
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
assert_true(!str_contains($encPage['body'], 'for="mode">Mode'), 'Mode heading is removed');
assert_true(
    (bool) preg_match('/<select[^>]*id="mode"[^>]*>[\s\S]*?<option value="encrypt" selected>/', $encPage['body']),
    'default mode is Encrypt'
);
assert_true(
    (bool) preg_match('/<select[^>]*id="mode"[^>]*>[\s\S]*?<option value="decrypt">/', $encPage['body']),
    'Decrypt is selectable in the mode dropdown'
);
assert_true(
    (bool) preg_match(
        '/role="group"[^>]*aria-label="Encrypt or decrypt action"[\s\S]*?id="mode"[\s\S]*?<button[^>]*id="run"/',
        $encPage['body']
    ),
    'mode dropdown and action button form one control group'
);
assert_true(str_contains($encPage['body'], 'sm:w-fit'), 'encrypt control group stays compact on desktop');
assert_true(str_contains($encPage['body'], 'sm:w-36'), 'mode dropdown uses a compact desktop width');
assert_true(str_contains($encPage['body'], 'h-11'), 'encrypt controls use matching control height');
assert_true(!str_contains($encPage['body'], 'Encrypt-V1'), 'Encrypt-V1 is not visible in the UI');
assert_true(!str_contains($encPage['body'], 'value="encrypt-v1"'), 'Encrypt-V1 is not a mode option');
assert_true(str_contains($encPage['body'], 'Compact Encrypted Text'), 'Encrypt UI includes compact output card');
assert_true(str_contains($encPage['body'], 'Encrypted JSON / Object'), 'Encrypt UI includes JSON output card');
assert_true(substr_count($encPage['body'], '>Copy</button>') >= 3, 'Encrypt/Decrypt copy buttons are labeled Copy');
assert_true(!str_contains($encPage['body'], 'Copy encrypted'), 'long Copy labels are not used');
assert_true(!str_contains($encPage['body'], 'Copy decrypted'), 'Decrypt copy button is labeled Copy');
assert_true(str_contains($encPage['body'], 'GET, POST'), 'encryption API docs list GET and POST');
assert_true(str_contains($encPage['body'], 'GET /api/encryption'), 'encryption API docs include a GET example');
assert_true(str_contains($encPage['body'], 'POST /api/encryption'), 'encryption API docs include a POST example');
assert_true(str_contains($encPage['body'], 'logged or cached'), 'encryption API docs warn about GET URLs');
assert_true(str_contains($encPage['body'], 'absolute right-2 top-2'), 'copy icon is absolutely positioned top-right');
assert_true(str_contains($encPage['body'], 'pt-11') && str_contains($encPage['body'], 'pr-12'), 'example code has top and right padding for the copy icon');
assert_true(
    (bool) preg_match('/data-copy-target="#apiExample-encryption"[^>]*aria-label="Copy example"|aria-label="Copy example"[^>]*data-copy-target="#apiExample-encryption"/', $encPage['body'])
    || (str_contains($encPage['body'], 'data-copy-target="#apiExample-encryption"') && str_contains($encPage['body'], 'aria-label="Copy example"')),
    'API example box includes an inline copy control'
);
assert_true(!str_contains($encPage['body'], 'flex items-center justify-end'), 'copy icon is not in a left-default flex toolbar');
assert_true(!str_contains($encPage['body'], 'Copy API Example'), 'full-width Copy API Example button is removed');
assert_true(!str_contains($encPage['body'], 'JSON envelope:'), 'repetitive envelope warning is removed from tool API cards');
assert_true(!str_contains($encPage['body'], 'Result fields live only inside data'), 'repetitive envelope prose is removed from tool API cards');

$apiExamplePages = ['password', 'hash', 'timestamp', 'uuid', 'base64', 'user-agent', 'ip', 'secret', 'encryption'];
foreach ($apiExamplePages as $toolPage) {
    $toolHtml = $toolPage === 'encryption' ? $encPage : http_request('GET', $base . '/' . $toolPage);
    assert_true($toolHtml['status'] === 200, 'GET /' . $toolPage . ' for API example copy icon');
    assert_true(
        str_contains($toolHtml['body'], 'absolute right-2 top-2')
            && str_contains($toolHtml['body'], 'data-copy-mode="icon"')
            && str_contains($toolHtml['body'], 'aria-label="Copy example"')
            && str_contains($toolHtml['body'], 'data-copy-target="#apiExample-' . $toolPage . '"')
            && str_contains($toolHtml['body'], 'pt-11')
            && str_contains($toolHtml['body'], 'pr-12')
            && str_contains($toolHtml['body'], 'pl-4'),
        '/' . $toolPage . ' API example copy icon is top-right with padded code'
    );
}

$appJs = http_request('GET', $base . '/assets/app.js');
assert_true($appJs['status'] === 200, 'GET /assets/app.js');
assert_true(
    str_contains($appJs['body'], "['hash', 'base64', 'encryption'].includes(tool) ? 'POST' : 'GET'"),
    'browser api() defaults hash/base64/encryption to POST'
);
assert_true(
    (bool) preg_match("/api\(\s*\{[\s\S]*?tool:\s*'hash'[\s\S]*?\},\s*'POST'\s*\)/", $appJs['body']),
    'hash UI sends user input via POST'
);
assert_true(
    (bool) preg_match("/api\(\s*\{\s*tool:\s*'ip'\s*\}\s*\)/", $appJs['body']),
    'IP lookup remains a GET with no user payload'
);
assert_true(
    str_contains($appJs['body'], "const path = '/api/' + encodeURIComponent(tool);"),
    'browser api() calls /api/{tool} so POST can resolve the tool'
);

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
assert_api_envelope($uuid['json'], 'GET /api/uuid', true);
assert_true($uuid['status'] === 200 && ($uuid['json']['ok'] ?? null) === true, 'GET /api/uuid');
assert_true(isset(api_data($uuid['json'])['uuid']), 'uuid lives in data');
assert_true(!array_key_exists('uuid', $uuid['json'] ?? []), 'uuid is not duplicated at root');
assert_true(header_has($uuid['headers'], 'cache-control', 'no-store'), 'GET API responses are not cacheable');
assert_true(header_has($uuid['headers'], 'x-content-type-options', 'nosniff'), 'API sends nosniff');

$ip = http_request('GET', $base . '/api/ip', null, [
    'X-Forwarded-For' => '8.8.8.8',
    'X-Real-IP' => '1.1.1.1',
]);
assert_api_envelope($ip['json'], 'GET /api/ip', true);
assert_true($ip['status'] === 200, 'GET /api/ip');
$ipData = api_data($ip['json']);
assert_true(($ipData['proxy_headers']['trusted'] ?? null) === false, 'proxy headers are not trusted');
assert_true(($ipData['ip'] ?? null) !== '8.8.8.8', 'X-Forwarded-For is not used as client IP');
assert_true(!array_key_exists('ip', $ip['json'] ?? []), 'ip is not duplicated at root');

$hashGet = http_request('GET', $base . '/api/hash?str=admin123&algorithm=sha256');
assert_api_envelope($hashGet['json'], 'GET /api/hash', true);
assert_true($hashGet['status'] === 200 && isset(api_data($hashGet['json'])['hash']), 'GET /api/hash sha256');
assert_true(!array_key_exists('hash', $hashGet['json'] ?? []), 'GET hash is not duplicated at root');
assert_true(header_has($hashGet['headers'], 'cache-control', 'no-store'), 'GET /api/hash is not cacheable');

$badAlg = http_request('GET', $base . '/api/hash?str=admin123&algorithm=nope');
assert_api_envelope($badAlg['json'], 'unsupported algorithm', false);
assert_true($badAlg['status'] === 400, 'unsupported algorithm is 400');
assert_true(api_error_code($badAlg['json']) === 'INVALID_PARAMETER', 'unsupported algorithm uses INVALID_PARAMETER');
assert_true(api_error_message($badAlg['json']) === 'Unsupported algorithm', 'unsupported algorithm message is stable');

$putPassword = http_request('PUT', $base . '/api/password');
assert_true($putPassword['status'] === 405, 'PUT /api/password is 405');
assert_true(header_has($putPassword['headers'], 'allow', 'GET'), 'PUT /api/password Allow includes GET');
assert_true(api_error_code($putPassword['json']) === 'METHOD_NOT_ALLOWED', 'PUT uses METHOD_NOT_ALLOWED');

$badJson = http_request('POST', $base . '/api/hash', '{', [
    'Content-Type' => 'application/json',
]);
assert_api_envelope($badJson['json'], 'malformed JSON', false);
assert_true($badJson['status'] === 400 && ($badJson['json']['ok'] ?? null) === false, 'malformed JSON is 400');
assert_true(api_error_code($badJson['json']) === 'INVALID_JSON', 'malformed JSON uses INVALID_JSON');

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

$hashViaApiPhp = http_request('POST', $base . '/api.php', json_encode([
    'tool' => 'hash',
    'str' => 'admin123',
    'algorithm' => 'sha256',
]), ['Content-Type' => 'application/json']);
assert_api_envelope($hashViaApiPhp['json'], 'POST /api.php JSON tool=hash', true);
assert_true($hashViaApiPhp['status'] === 200 && isset(api_data($hashViaApiPhp['json'])['hash']), 'POST /api.php with tool in JSON body hashes');
assert_true(($hashViaApiPhp['json']['tool'] ?? null) === 'hash', 'POST /api.php JSON tool is hash');

$hashOk = http_request('POST', $base . '/api/hash', json_encode(['str' => 'admin123', 'algorithm' => 'sha256']), [
    'Content-Type' => 'application/json',
]);
assert_api_envelope($hashOk['json'], 'POST /api/hash', true);
assert_true($hashOk['status'] === 200 && isset(api_data($hashOk['json'])['hash']), 'POST /api/hash sha256');
assert_true(!array_key_exists('hash', $hashOk['json'] ?? []), 'hash is not duplicated at root');
assert_true(header_has($hashOk['headers'], 'cache-control', 'no-store'), 'POST /api/hash is not cacheable');
assert_true(
    (api_data($hashGet['json'])['hash'] ?? null) === (api_data($hashOk['json'])['hash'] ?? null),
    'GET and POST /api/hash return the same digest'
);

$b64Get = http_request('GET', $base . '/api/base64?str=hello&mode=encode');
assert_api_envelope($b64Get['json'], 'GET /api/base64', true);
assert_true($b64Get['status'] === 200 && (api_data($b64Get['json'])['output'] ?? null) === base64_encode('hello'), 'GET /api/base64');
assert_true(!array_key_exists('output', $b64Get['json'] ?? []), 'GET base64 output is not duplicated at root');

$b64 = http_request('POST', $base . '/api/base64', json_encode(['str' => 'hello', 'mode' => 'encode']), [
    'Content-Type' => 'application/json',
]);
assert_api_envelope($b64['json'], 'POST /api/base64', true);
assert_true($b64['status'] === 200 && (api_data($b64['json'])['output'] ?? null) === base64_encode('hello'), 'POST /api/base64');
assert_true(!array_key_exists('output', $b64['json'] ?? []), 'base64 output is not duplicated at root');

$password = http_request('GET', $base . '/api/password?length=16');
assert_api_envelope($password['json'], 'GET /api/password', true);
assert_true(isset(api_data($password['json'])['password']), 'password lives in data');
assert_true(!array_key_exists('password', $password['json'] ?? []), 'password is not duplicated at root');

$secret = http_request('GET', $base . '/api/secret?length=32');
assert_api_envelope($secret['json'], 'GET /api/secret', true);
assert_true(isset(api_data($secret['json'])['secret']), 'secret lives in data');
assert_true(!array_key_exists('secret', $secret['json'] ?? []), 'secret is not duplicated at root');

$tsSchema = http_request('GET', $base . '/api/timestamp');
assert_api_envelope($tsSchema['json'], 'GET /api/timestamp schema', true);
assert_true(isset(api_data($tsSchema['json'])['unix_seconds']), 'timestamp fields live in data');
assert_true(!array_key_exists('unix_seconds', $tsSchema['json'] ?? []), 'timestamp is not duplicated at root');

$ua = http_request('GET', $base . '/api/user-agent?ua=' . rawurlencode('Mozilla/5.0 Firefox/128.0'));
assert_api_envelope($ua['json'], 'GET /api/user-agent', true);
assert_true(isset(api_data($ua['json'])['browser']), 'user-agent fields live in data');
assert_true(!array_key_exists('browser', $ua['json'] ?? []), 'user-agent is not duplicated at root');

reset_rate_limit_files();

$enc = http_request('POST', $base . '/api/encryption', json_encode([
    'str' => 'hello',
    'key' => 'unit-test-key',
    'mode' => 'encrypt',
]), ['Content-Type' => 'application/json']);

$opensslOk = $enc['status'] === 200 && is_array(api_data($enc['json'])['json'] ?? null);
assert_true($opensslOk || str_contains(api_error_message($enc['json']), 'OpenSSL'), 'POST /api/encryption encrypt or OpenSSL unavailable');

if ($opensslOk) {
    assert_api_envelope($enc['json'], 'POST /api/encryption encrypt', true);
    $encData = api_data($enc['json']);
    $payload = $encData['json'];
    $compact = (string) ($encData['compact'] ?? '');
    assert_true(($payload['v'] ?? null) === 2, 'encryption payload version is 2');
    assert_true(($encData['version'] ?? null) === 2, 'encrypt response reports version 2');
    assert_true(($payload['alg'] ?? null) === 'AES-256-GCM', 'encryption algorithm is AES-256-GCM');
    assert_true($compact !== '' && (bool) preg_match('#^[A-Za-z0-9+/]+=*$#', $compact), 'encrypt returns compact Base64');
    assert_true(is_array($encData['json'] ?? null), 'encrypt returns json object');
    assert_true(!array_key_exists('payload', $encData), 'encrypt does not return redundant payload alias');
    assert_true(!array_key_exists('output', $encData), 'encrypt does not return pretty-printed output string');
    assert_true(!array_key_exists('compact', $enc['json'] ?? []), 'compact is not duplicated at root');
    assert_true(!str_contains($enc['body'], 'unit-test-key'), 'encrypt response does not contain the secret');
    assert_true(!str_contains($enc['body'], '"hello"'), 'encrypt response does not contain plaintext');

    reset_rate_limit_files();
    $encGet = http_request(
        'GET',
        $base . '/api/encryption?str=hello&key=' . rawurlencode('unit-test-key') . '&mode=encrypt'
    );
    assert_api_envelope($encGet['json'], 'GET /api/encryption encrypt', true);
    assert_true($encGet['status'] === 200, 'GET /api/encryption encrypt');
    $encGetData = api_data($encGet['json']);
    assert_true(($encGetData['version'] ?? null) === 2 && is_string($encGetData['compact'] ?? null), 'GET encryption returns compact V2');
    assert_true(!array_key_exists('compact', $encGet['json'] ?? []), 'GET encryption compact is not duplicated at root');
    assert_true(!str_contains($encGet['body'], 'unit-test-key'), 'GET encrypt response does not contain the secret');

    $defaultV = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => 'hello',
        'key' => 'unit-test-key',
        'mode' => 'encrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true(
        (api_data($defaultV['json'])['version'] ?? null) === 2
        && ((api_data($defaultV['json'])['json']['v'] ?? null) === 2),
        'omitted v defaults to 2'
    );

    $explicitV2 = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => 'hello',
        'key' => 'unit-test-key',
        'mode' => 'encrypt',
        'v' => 2,
    ]), ['Content-Type' => 'application/json']);
    assert_true(
        (api_data($explicitV2['json'])['version'] ?? null) === 2
        && ((api_data($explicitV2['json'])['json']['v'] ?? null) === 2),
        'v=2 uses V2 encryption'
    );

    foreach ([3, 99, 'abc'] as $badV) {
        reset_rate_limit_files();
        $bad = http_request('POST', $base . '/api/encryption', json_encode([
            'str' => 'hello',
            'key' => 'unit-test-key',
            'mode' => 'encrypt',
            'v' => $badV,
        ]), ['Content-Type' => 'application/json']);
        assert_api_envelope($bad['json'], 'unsupported v=' . json_encode($badV), false);
        assert_true($bad['status'] === 400, 'unsupported v=' . json_encode($badV) . ' is rejected');
    }

    reset_rate_limit_files();
    $legacyMode = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => 'hello',
        'key' => 'unit-test-key',
        'mode' => 'encrypt-v1',
    ]), ['Content-Type' => 'application/json']);
    assert_true($legacyMode['status'] === 400, 'mode=encrypt-v1 is rejected');

    reset_rate_limit_files();

    $round = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => json_encode($payload),
        'key' => 'unit-test-key',
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_api_envelope($round['json'], 'decrypt JSON', true);
    assert_true($round['status'] === 200 && (api_data($round['json'])['output'] ?? null) === 'hello', 'encryption round-trip');
    assert_true(!array_key_exists('output', $round['json'] ?? []), 'decrypt output is not duplicated at root');

    $compactRound = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => $compact,
        'key' => 'unit-test-key',
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true(
        $compactRound['status'] === 200 && (api_data($compactRound['json'])['output'] ?? null) === 'hello',
        'compact Base64 decrypts to the same plaintext'
    );

    reset_rate_limit_files();

    $strong = bin2hex(random_bytes(24));
    $strongEnc = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => "hello 🔐 café\n{\"a\":1}",
        'key' => $strong,
        'mode' => 'encrypt',
    ]), ['Content-Type' => 'application/json']);
    $strongData = api_data($strongEnc['json']);
    $strongCompact = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => $strongData['compact'] ?? '',
        'key' => $strong,
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    $strongJson = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => json_encode($strongData['json'] ?? new stdClass()),
        'key' => $strong,
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true(
        $strongEnc['status'] === 200
        && (api_data($strongCompact['json'])['output'] ?? null) === "hello 🔐 café\n{\"a\":1}"
        && (api_data($strongJson['json'])['output'] ?? null) === "hello 🔐 café\n{\"a\":1}",
        '48-char hex secret round-trips compact and JSON to the same plaintext'
    );

    $wrong = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => json_encode($payload),
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
    $v1Data = api_data($v1['json']);
    assert_true($v1['status'] === 200 && (($v1Data['json']['v'] ?? null) === 1), 'v=1 writes payload version 1');
    assert_true(($v1Data['version'] ?? null) === 1, 'v=1 response reports version 1');
    assert_true(is_string($v1Data['compact'] ?? null) && ($v1Data['compact'] ?? '') !== '', 'v=1 also returns compact');

    $v1Round = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => json_encode($v1Data['json'] ?? new stdClass()),
        'key' => 'unit-test-key',
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true(
        $v1Round['status'] === 200 && (api_data($v1Round['json'])['output'] ?? null) === "hello 🔐 café\n{\"a\":1}",
        'decrypt auto-detects V1 JSON payload'
    );

    $v1CompactRound = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => $v1Data['compact'] ?? '',
        'key' => 'unit-test-key',
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true(
        $v1CompactRound['status'] === 200 && (api_data($v1CompactRound['json'])['output'] ?? null) === "hello 🔐 café\n{\"a\":1}",
        'decrypt auto-detects V1 compact payload'
    );

    $again = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => 'hello',
        'key' => 'unit-test-key',
        'mode' => 'encrypt',
    ]), ['Content-Type' => 'application/json']);
    $againData = api_data($again['json']);
    assert_true(
        $again['status'] === 200
        && (($againData['json']['ct'] ?? null) !== ($payload['ct'] ?? null))
        && (($againData['json']['salt'] ?? null) !== ($payload['salt'] ?? null))
        && (($againData['compact'] ?? null) !== $compact),
        'repeated encrypt produces different ciphertext and salt'
    );

    $empty = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => '',
        'key' => 'unit-test-key',
        'mode' => 'encrypt',
    ]), ['Content-Type' => 'application/json']);
    $emptyRound = http_request('POST', $base . '/api/encryption', json_encode([
        'str' => api_data($empty['json'])['compact'] ?? '',
        'key' => 'unit-test-key',
        'mode' => 'decrypt',
    ]), ['Content-Type' => 'application/json']);
    assert_true(
        $empty['status'] === 200
        && $emptyRound['status'] === 200
        && (api_data($emptyRound['json'])['output'] ?? null) === '',
        'empty plaintext round-trips'
    );

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
    assert_api_envelope($retry['json'], '429 response', false);
    assert_true(api_error_code($retry['json']) === 'RATE_LIMITED', '429 uses RATE_LIMITED');
    assert_true(isset($retry['headers']['retry-after']), '429 includes Retry-After');
    assert_true(header_has($retry['headers'], 'cache-control', 'no-store'), '429 is not cacheable');
}

$ts = http_request('GET', $base . '/api/timestamp');
assert_true($ts['status'] === 200, 'cheap GET /api/timestamp remains available');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
