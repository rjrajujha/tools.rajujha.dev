<?php
declare(strict_types=1);

namespace App\Services;

final class UserAgentService
{
    public static function handle(): never
    {
        $ua = param('ua', param('user_agent', $_SERVER['HTTP_USER_AGENT'] ?? ''));
        assert_size($ua, 'ua');

        if ($ua === '') {
            fail('Missing User-Agent. Pass ua=... or call from a browser.', 400, 'user-agent', 'MISSING_PARAMETER');
        }

        ok('user-agent', parseUserAgent($ua));
    }
}
