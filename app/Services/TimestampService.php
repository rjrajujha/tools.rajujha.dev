<?php
declare(strict_types=1);

namespace App\Services;

final class TimestampService
{
    public static function handle(): never
    {
        $raw = param('timestamp', param('ts'));

        if ($raw === '') {
            $now = microtime(true);
            ok('timestamp', [
                'current' => true,
                'timezone' => 'UTC',
                'unix_seconds' => (int) floor($now),
                'unix_milliseconds' => (int) round($now * 1000),
                'iso_8601' => gmdate('c'),
                'utc' => gmdate('Y-m-d H:i:s') . ' UTC',
            ]);
        }

        if (!is_numeric($raw)) {
            fail('timestamp must be numeric', 400, 'timestamp');
        }

        $unit = strtolower(param('unit', 's'));
        if (!in_array($unit, ['s', 'ms'], true)) {
            fail('unit must be s or ms', 400, 'timestamp');
        }

        $sec = $unit === 'ms' ? (float) $raw / 1000 : (float) $raw;
        $whole = (int) floor($sec);
        $fraction = max(0.0, $sec - $whole);

        ok('timestamp', [
            'input' => $raw,
            'unit' => $unit,
            'unix_seconds' => $sec,
            'unix_milliseconds' => (int) round($sec * 1000),
            'iso_8601' => gmdate('Y-m-d\TH:i:s', $whole) . sprintf('.%03dZ', (int) round($fraction * 1000)),
            'utc' => gmdate('Y-m-d H:i:s', $whole) . ' UTC',
        ]);
    }
}
