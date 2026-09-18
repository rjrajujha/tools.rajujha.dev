<?php
declare(strict_types=1);

namespace App\Services;

final class EncryptionService
{
    public static function handle(): never
    {
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
}
