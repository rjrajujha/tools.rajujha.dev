<?php
declare(strict_types=1);

namespace App\Services;

final class PasswordService
{
    public static function handle(): never
    {
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
}
