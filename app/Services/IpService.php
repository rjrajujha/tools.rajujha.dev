<?php
declare(strict_types=1);

namespace App\Services;

final class IpService
{
    public static function handle(): never
    {
        $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $family = $remote !== '' ? classifyIp($remote) : null;
        $ipv4 = $family === 'ipv4' ? $remote : null;
        $ipv6 = $family === 'ipv6' ? $remote : null;
        $xRealIp = trim((string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''));
        $xForwardedFor = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));

        ok('ip', [
            'ip' => $remote !== '' ? $remote : null,
            'version' => $family === 'ipv4' ? 4 : ($family === 'ipv6' ? 6 : null),
            'ipv4' => $ipv4,
            'ipv6' => $ipv6,
            'proxy_headers' => [
                'trusted' => false,
                'x_real_ip' => $xRealIp !== '' ? $xRealIp : null,
                'x_forwarded_for' => $xForwardedFor !== '' ? $xForwardedFor : null,
            ],
        ]);
    }
}
