<?php
declare(strict_types=1);

namespace App\Services;

final class SecretService
{
    public static function handle(): never
    {
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
}
