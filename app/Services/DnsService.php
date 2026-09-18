<?php
declare(strict_types=1);

namespace App\Services;

final class DnsService
{
    private const PROVIDERS = [
        'cloudflare' => [
            'url' => 'https://cloudflare-dns.com/dns-query',
            'accept' => 'application/dns-json',
        ],
        'google' => [
            'url' => 'https://dns.google/resolve',
            'accept' => 'application/dns-json',
        ],
    ];

    public static function handle(): never
    {
        $host = trim(param('host', param('name')));
        if ($host === '') {
            fail('Missing parameter: host', 400, 'dns', 'MISSING_PARAMETER');
        }
        assert_size($host, 'host');
        if (!valid_dns_host($host)) {
            fail('host must be a valid hostname', 400, 'dns');
        }

        $type = strtoupper(trim(param('type', 'A')));
        $allowedTypes = ['A', 'AAAA', 'MX', 'TXT', 'CNAME', 'NS'];
        if (!in_array($type, $allowedTypes, true)) {
            fail('type must be A, AAAA, MX, TXT, CNAME, or NS', 400, 'dns');
        }

        $requested = strtolower(trim(param('provider', 'cloudflare')));
        if ($requested === '') {
            $requested = 'cloudflare';
        }
        if (!isset(self::PROVIDERS[$requested])) {
            fail('provider must be cloudflare or google', 400, 'dns');
        }

        $host = rtrim($host, '.');
        $explicit = array_key_exists('provider', request_params());
        $order = $explicit ? [$requested] : ['cloudflare', 'google'];

        foreach ($order as $provider) {
            try {
                ok('dns', self::lookupJson($host, $type, $provider));
            } catch (\Throwable) {
                // Try the next resolver when the caller did not pin a provider.
            }
        }

        fail('DNS lookup failed. Try again shortly.', 502, 'dns', 'LOOKUP_FAILED');
    }

    /**
     * @return array{provider: string, host: string, type: string, status: int, status_name: string, answers: list<array{name: string, type: string, ttl: int, data: string}>}
     */
    private static function lookupJson(string $host, string $type, string $provider): array
    {
        $spec = self::PROVIDERS[$provider];
        $query = $spec['url'] . '?' . http_build_query([
            'name' => $host,
            'type' => $type,
        ], '', '&', PHP_QUERY_RFC3986);

        $response = app_http_get($query, [
            'Accept' => $spec['accept'],
        ]);

        if (!$response['ok']) {
            throw new \RuntimeException('JSON DoH failed');
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid JSON DoH response');
        }

        $status = filter_var($decoded['Status'] ?? 0, FILTER_VALIDATE_INT);
        if ($status === false) {
            $status = 0;
        }

        $answers = [];
        $rawAnswers = $decoded['Answer'] ?? [];
        if (is_array($rawAnswers)) {
            foreach ($rawAnswers as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $answerType = filter_var($item['type'] ?? 0, FILTER_VALIDATE_INT);
                $ttl = filter_var($item['TTL'] ?? 0, FILTER_VALIDATE_INT);
                $answers[] = [
                    'name' => (string) ($item['name'] ?? ''),
                    'type' => dns_type_name($answerType === false ? 0 : $answerType),
                    'ttl' => $ttl === false ? 0 : $ttl,
                    'data' => (string) ($item['data'] ?? ''),
                ];
                if (count($answers) >= 8) {
                    break;
                }
            }
        }

        return [
            'provider' => $provider,
            'host' => $host,
            'type' => $type,
            'status' => $status,
            'status_name' => dns_status_name($status),
            'answers' => $answers,
        ];
    }
}
