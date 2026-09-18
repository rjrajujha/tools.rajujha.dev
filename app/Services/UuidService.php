<?php
declare(strict_types=1);

namespace App\Services;

final class UuidService
{
    public static function handle(): never
    {
        $count = filter_var(param('count', '1'), FILTER_VALIDATE_INT);
        if ($count === false || $count < 1 || $count > 100) {
            fail('count must be an integer from 1 to 100', 400, 'uuid');
        }

        $uuids = [];
        for ($i = 0; $i < $count; $i++) {
            $uuids[] = generateUuidV4();
        }

        ok('uuid', [
            'version' => 4,
            'count' => $count,
            'uuid' => $uuids[0],
            'uuids' => $uuids,
        ]);
    }
}
