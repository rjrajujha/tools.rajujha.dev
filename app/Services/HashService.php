<?php
declare(strict_types=1);

namespace App\Services;

final class HashService
{
    public static function handle(): never
    {
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
}
