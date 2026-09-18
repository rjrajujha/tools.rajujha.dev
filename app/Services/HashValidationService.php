<?php
declare(strict_types=1);

namespace App\Services;

final class HashValidationService
{
    public static function handle(): never
    {
        $params = request_params();
        if (!isset($params['str']) && !isset($params['string']) && !isset($params['input'])) {
            fail('Missing parameter: str', 400, 'hash-validation', 'MISSING_PARAMETER');
        }
        if (!isset($params['hash'])) {
            fail('Missing parameter: hash', 400, 'hash-validation', 'MISSING_PARAMETER');
        }

        $str = inputValue();
        $hash = param('hash');
        assert_size($str, 'str');
        assert_size($hash, 'hash');

        if (trim($hash) === '') {
            fail('hash is required', 400, 'hash-validation', 'MISSING_PARAMETER');
        }

        $algorithm = strtolower(trim(param('algorithm', 'auto')));
        $allowed = ['auto', 'sha256', 'sha384', 'sha512', 'sha1', 'md5', 'bcrypt'];
        if (!in_array($algorithm, $allowed, true)) {
            fail('Unsupported algorithm', 400, 'hash-validation');
        }

        $trimmedHash = trim($hash);
        $auto = $algorithm === 'auto';
        $resolved = $algorithm;

        if ($auto) {
            if (looks_like_bcrypt($trimmedHash)) {
                $resolved = 'bcrypt';
            } else {
                $detected = detect_digest_algorithm($trimmedHash);
                $resolved = $detected ?? 'auto';
            }
        }

        if ($resolved === 'bcrypt') {
            if (!looks_like_bcrypt($trimmedHash) && !$auto) {
                fail('Invalid bcrypt hash', 400, 'hash-validation');
            }

            $match = looks_like_bcrypt($trimmedHash) && password_verify($str, $trimmedHash);
            ok('hash-validation', [
                'match' => $match,
                'algorithm' => 'bcrypt',
                'auto' => $auto,
            ]);
        }

        $digestAlgs = ['md5', 'sha1', 'sha256', 'sha384', 'sha512'];
        if ($resolved === 'auto') {
            foreach ($digestAlgs as $candidate) {
                if (hash_validation_compare($str, $trimmedHash, $candidate)) {
                    ok('hash-validation', [
                        'match' => true,
                        'algorithm' => $candidate,
                        'auto' => true,
                    ]);
                }
            }

            ok('hash-validation', [
                'match' => false,
                'algorithm' => 'auto',
                'auto' => true,
            ]);
        }

        if (!in_array($resolved, $digestAlgs, true)) {
            fail('Unsupported algorithm', 400, 'hash-validation');
        }

        ok('hash-validation', [
            'match' => hash_validation_compare($str, $trimmedHash, $resolved),
            'algorithm' => $resolved,
            'auto' => $auto,
        ]);
    }
}
