<?php
declare(strict_types=1);

namespace App\Services;

final class Base64Service
{
    public static function handle(): never
    {
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
}
