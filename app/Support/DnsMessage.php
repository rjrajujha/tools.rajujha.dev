<?php
declare(strict_types=1);

namespace App\Support;

/**
 * RFC 8484 DNS-over-HTTPS wire messages (AdGuard Home /dns-query).
 */
final class DnsMessage
{
    public const TYPES = [
        'A' => 1,
        'NS' => 2,
        'CNAME' => 5,
        'MX' => 15,
        'TXT' => 16,
        'AAAA' => 28,
    ];

    /**
     * @return array<int, string>
     */
    public static function typeNames(): array
    {
        return array_flip(self::TYPES);
    }

    public static function encodeQuery(string $name, string $type): string
    {
        $qtype = self::TYPES[$type] ?? 1;
        $header = pack('nnnnnn', random_int(0, 65535), 0x0100, 1, 0, 0, 0);

        return $header . self::encodeName($name) . pack('nn', $qtype, 1);
    }

    public static function base64url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    /**
     * @return list<array{name: string, type: string, ttl: int, data: string}>
     */
    public static function decodeAnswers(string $message): array
    {
        $length = strlen($message);
        if ($length < 12) {
            throw new \RuntimeException('DNS message is too short');
        }

        $header = unpack('nid/nflags/nqd/nan/nns/nar', substr($message, 0, 12));
        if (!is_array($header)) {
            throw new \RuntimeException('Invalid DNS header');
        }

        $offset = 12;
        $qd = (int) $header['qd'];
        for ($i = 0; $i < $qd; $i++) {
            self::decodeName($message, $offset);
            $offset += 4;
            if ($offset > $length) {
                throw new \RuntimeException('Truncated DNS question');
            }
        }

        $names = self::typeNames();
        $answers = [];
        $an = (int) $header['an'];
        for ($i = 0; $i < $an && count($answers) < 8; $i++) {
            $recordName = self::decodeName($message, $offset);
            if ($offset + 10 > $length) {
                throw new \RuntimeException('Truncated DNS record');
            }
            $meta = unpack('ntype/nclass/Nttl/nrdlength', substr($message, $offset, 10));
            if (!is_array($meta)) {
                throw new \RuntimeException('Invalid DNS record');
            }
            $offset += 10;
            $rdlength = (int) $meta['rdlength'];
            if ($offset + $rdlength > $length) {
                throw new \RuntimeException('Truncated DNS rdata');
            }
            $rdata = substr($message, $offset, $rdlength);
            $rdataOffset = $offset;
            $offset += $rdlength;
            $type = (int) $meta['type'];
            $answers[] = [
                'name' => $recordName,
                'type' => $names[$type] ?? (string) $type,
                'ttl' => (int) $meta['ttl'],
                'data' => self::decodeRdata($message, $rdataOffset, $rdata, $type),
            ];
        }

        return $answers;
    }

    public static function responseCode(string $message): int
    {
        if (strlen($message) < 4) {
            return 2;
        }
        $flags = unpack('n', substr($message, 2, 2));

        return is_array($flags) ? ((int) $flags[1] & 0x0F) : 2;
    }

    private static function encodeName(string $name): string
    {
        $normalized = strtolower(rtrim($name, '.'));
        if ($normalized === '') {
            return "\0";
        }

        $out = '';
        foreach (explode('.', $normalized) as $label) {
            $len = strlen($label);
            if ($len < 1 || $len > 63) {
                throw new \InvalidArgumentException('Invalid DNS label');
            }
            $out .= chr($len) . $label;
        }

        return $out . "\0";
    }

    private static function decodeName(string $message, int &$offset, int $depth = 0): string
    {
        if ($depth > 10) {
            throw new \RuntimeException('DNS name pointer loop');
        }

        $length = strlen($message);
        $labels = [];
        $jumped = false;
        $returnOffset = $offset;

        while ($offset < $length) {
            $len = ord($message[$offset]);
            if ($len === 0) {
                if (!$jumped) {
                    $offset++;
                } else {
                    $offset = $returnOffset;
                }
                break;
            }

            if (($len & 0xC0) === 0xC0) {
                if ($offset + 1 >= $length) {
                    throw new \RuntimeException('Truncated DNS pointer');
                }
                $pointer = (($len & 0x3F) << 8) | ord($message[$offset + 1]);
                if (!$jumped) {
                    $returnOffset = $offset + 2;
                    $jumped = true;
                }
                $offset = $pointer;
                $depth++;
                if ($depth > 10) {
                    throw new \RuntimeException('DNS name pointer loop');
                }
                continue;
            }

            if (($len & 0xC0) !== 0) {
                throw new \RuntimeException('Invalid DNS label');
            }

            $offset++;
            if ($offset + $len > $length) {
                throw new \RuntimeException('Truncated DNS label');
            }
            $labels[] = substr($message, $offset, $len);
            $offset += $len;
        }

        if ($jumped) {
            $offset = $returnOffset;
        }

        return implode('.', $labels);
    }

    private static function decodeRdata(string $message, int $offset, string $rdata, int $type): string
    {
        if ($type === 1 && strlen($rdata) === 4) {
            $ip = inet_ntop($rdata);
            return is_string($ip) ? $ip : bin2hex($rdata);
        }
        if ($type === 28 && strlen($rdata) === 16) {
            $ip = inet_ntop($rdata);
            return is_string($ip) ? $ip : bin2hex($rdata);
        }
        if ($type === 15 && strlen($rdata) >= 3) {
            $pref = unpack('n', substr($rdata, 0, 2));
            $nameOffset = $offset + 2;
            $exchange = self::decodeName($message, $nameOffset);

            return ((int) ($pref[1] ?? 0)) . ' ' . $exchange;
        }
        if ($type === 16) {
            $out = '';
            $i = 0;
            $len = strlen($rdata);
            while ($i < $len) {
                $size = ord($rdata[$i]);
                $i++;
                $out .= substr($rdata, $i, $size);
                $i += $size;
            }

            return $out;
        }
        if ($type === 2 || $type === 5) {
            $nameOffset = $offset;

            return self::decodeName($message, $nameOffset);
        }

        return bin2hex($rdata);
    }
}
