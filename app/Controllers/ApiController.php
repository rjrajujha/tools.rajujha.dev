<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Base64Service;
use App\Services\DnsService;
use App\Services\EncryptionService;
use App\Services\HashService;
use App\Services\HashValidationService;
use App\Services\IpService;
use App\Services\PasswordService;
use App\Services\SecretService;
use App\Services\SshService;
use App\Services\TimestampService;
use App\Services\UserAgentService;
use App\Services\UuidService;

final class ApiController
{
    public static function dispatch(): never
    {
        require_once APP_ROOT . '/app/Http/helpers.php';

        header('Content-Type: application/json; charset=utf-8');
        app_no_store_headers();
        header('X-Content-Type-Options: nosniff');

        $tool = request_tool();

        if ($tool !== '' && !in_array(request_method(), ['GET', 'POST', 'HEAD'], true)) {
            reject_method($tool, ['GET', 'POST'], 'Method not allowed');
        }

        require_http_method($tool);

        if (in_array($tool, APP_RATE_LIMITED_TOOLS, true)) {
            app_rate_limit_enforce($tool);
        }

        match ($tool) {
            'password' => PasswordService::handle(),
            'hash' => HashService::handle(),
            'timestamp' => TimestampService::handle(),
            'uuid' => UuidService::handle(),
            'secret' => SecretService::handle(),
            'base64' => Base64Service::handle(),
            'user-agent' => UserAgentService::handle(),
            'ip' => IpService::handle(),
            'encryption' => EncryptionService::handle(),
            'hash-validation' => HashValidationService::handle(),
            'ssh' => SshService::handle(),
            'dns' => DnsService::handle(),
            default => fail('Unknown tool.', 404),
        };
    }
}
