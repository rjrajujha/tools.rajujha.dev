<?php
declare(strict_types=1);

/**
 * Offline unit tests: config validation, rate limiter, security defaults.
 *
 *   php tests/run.php
 */

require_once dirname(__DIR__) . '/bootstrap.php';

$failed = 0;
$passed = 0;

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

$defaults = app_config_defaults();

$valid = app_normalize_config([
    'author' => 'Raju Jha',
    'version' => '1.0.0',
    'security' => $defaults['security'],
    'rate_limit' => $defaults['rate_limit'],
    'client_ip' => $defaults['client_ip'],
], $defaults);
assert_true(is_array($valid), 'valid config normalizes');
assert_true(($valid['rate_limit']['enabled'] ?? null) === true, 'rate_limit.enabled defaults to true');
assert_true(($valid['rate_limit']['requests'] ?? null) === 20, 'rate_limit.requests default is 20');
assert_true(($valid['rate_limit']['window_seconds'] ?? null) === 60, 'rate_limit.window_seconds default is 60');
assert_true(($valid['client_ip']['trust_cloudflare'] ?? null) === false, 'trust_cloudflare defaults to false');

$missingRate = app_normalize_config([
    'author' => 'Raju Jha',
    'version' => '1.0.0',
    'security' => $defaults['security'],
], $defaults);
assert_true(
    is_array($missingRate) && $missingRate['rate_limit']['enabled'] === true,
    'missing rate_limit still enables limiting'
);

$disabled = app_normalize_config([
    'author' => 'Raju Jha',
    'version' => '1.0.0',
    'security' => $defaults['security'],
    'rate_limit' => ['enabled' => false, 'requests' => 20, 'window_seconds' => 60],
], $defaults);
assert_true(is_array($disabled) && $disabled['rate_limit']['enabled'] === false, 'enabled:false is an explicit opt-out');

assert_true(app_normalize_config([
    'author' => 'Raju Jha',
    'version' => '1.0.0',
    'security' => $defaults['security'],
    'rate_limit' => ['enabled' => 'true', 'requests' => 20, 'window_seconds' => 60],
], $defaults) === null, 'string enabled is rejected');

assert_true(app_normalize_config([
    'author' => 'Raju Jha',
    'version' => '1.0.0',
    'security' => $defaults['security'],
    'rate_limit' => ['enabled' => true, 'requests' => 999999, 'window_seconds' => 60],
], $defaults) === null, 'absurd request count is rejected');

assert_true(app_normalize_config([
    'author' => 'Raju Jha',
    'version' => '1.0.0',
    'security' => $defaults['security'],
    'rate_limit' => ['enabled' => true, 'requests' => 20, 'window_seconds' => 1],
], $defaults) === null, 'too-short window is rejected');

assert_true(app_normalize_config([
    'author' => 'Raju Jha',
    'version' => '1.0.0',
    'security' => $defaults['security'],
    'rate_limit' => ['enabled' => true, 'requests' => 0, 'window_seconds' => 60],
], $defaults) === null, 'zero requests is rejected');

$tooHighBcrypt = app_normalize_config([
    'author' => 'Raju Jha',
    'version' => '1.0.0',
    'security' => [
        'bcrypt_cost' => 12,
        'max_bcrypt_cost' => 32,
        'encryption_iterations' => 310000,
        'max_encryption_iterations' => 310000,
    ],
], $defaults);
assert_true($tooHighBcrypt === null, 'bcrypt max above absolute 31 is rejected');

$tooHighIter = app_normalize_config([
    'author' => 'Raju Jha',
    'version' => '1.0.0',
    'security' => [
        'bcrypt_cost' => 12,
        'max_bcrypt_cost' => 14,
        'encryption_iterations' => 310000,
        'max_encryption_iterations' => 700000,
    ],
], $defaults);
assert_true($tooHighIter === null, 'encryption iterations above absolute max are rejected');

$unknownDropped = app_normalize_config([
    'author' => 'Raju Jha',
    'version' => '1.0.0',
    'security' => $defaults['security'],
    'api_key' => 'should-not-be-kept',
], $defaults);
assert_true(is_array($unknownDropped) && !array_key_exists('api_key', $unknownDropped), 'unknown config keys are dropped');

assert_true(app_strict_bool(true) === true && app_strict_bool('1') === null, 'config booleans are strict');

$loaded = app_config();
assert_true(is_string($loaded['version']) && $loaded['version'] !== '', 'loaded config version is present');
assert_true($loaded['version'] === '1.1.0', 'loaded config version is 1.1.0');
assert_true($loaded['rate_limit']['requests'] === 20, 'loaded rate_limit.requests is 20');
assert_true($loaded['client_ip']['trust_cloudflare'] === false, 'loaded trust_cloudflare is false');

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tools-rl-' . bin2hex(random_bytes(8));
assert_true(@mkdir($tmp, 0700, true) && is_dir($tmp), 'rate-limit temp directory created');
putenv('APP_RATE_LIMIT_DIR=' . $tmp);
$_ENV['APP_RATE_LIMIT_DIR'] = $tmp;

$allowed = 0;
$denied = 0;
$limit = $loaded['rate_limit']['requests'];
for ($i = 0; $i < $limit + 5; $i++) {
    $hit = app_rate_limit_hit();
    if ($hit['allowed']) {
        $allowed++;
    } else {
        $denied++;
        assert_true($hit['retry_after'] >= 1, '429 retry_after is at least 1 second');
    }
}
assert_true($allowed === $limit, 'rate limiter allows exactly the configured request count');
assert_true($denied === 5, 'rate limiter denies overflow requests');

$files = glob($tmp . DIRECTORY_SEPARATOR . '*.json') ?: [];
assert_true(count($files) === 1, 'rate limiter creates one hashed file per client');
$basename = basename($files[0]);
assert_true((bool) preg_match('/^[a-f0-9]{64}\\.json$/', $basename), 'rate-limit filename is a hex digest');
$payload = json_decode((string) file_get_contents($files[0]), true);
assert_true(is_array($payload) && !isset($payload['ip']), 'rate-limit file does not store the raw IP');

foreach ($files as $file) {
    @unlink($file);
}
@rmdir($tmp);
putenv('APP_RATE_LIMIT_DIR');
unset($_ENV['APP_RATE_LIMIT_DIR']);

assert_true(app_debug() === false, 'APP_DEBUG is off unless set in the server environment');

$client = public_client_config();
assert_true(
    str_starts_with((string) ($client['regexWorker'] ?? ''), '/assets/regex-worker.js'),
    'public config exposes a same-origin regex worker URL'
);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
