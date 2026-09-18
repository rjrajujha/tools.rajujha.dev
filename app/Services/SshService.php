<?php
declare(strict_types=1);

namespace App\Services;

final class SshService
{
    public static function handle(): never
    {
        $algorithm = strtolower(trim(param('algorithm', 'ed25519')));
        $comment = trim(param('comment'));
        $comment = str_replace(["\r", "\n"], '', $comment);
        if (strlen($comment) > 100) {
            fail('comment must be 100 characters or fewer', 400, 'ssh');
        }

        $passphrase = param('passphrase');
        $passphrase = str_replace(["\r", "\n"], '', $passphrase);
        if (strlen($passphrase) > 256) {
            fail('passphrase must be 256 characters or fewer', 400, 'ssh');
        }
        if ($passphrase !== '' && request_method() !== 'POST') {
            fail('Passphrase-protected keys require POST. Do not send passphrases in query strings.', 405, 'ssh');
        }

        $result = match ($algorithm) {
            'ed25519' => generate_ssh_ed25519($comment, $passphrase),
            'rsa2048' => generate_ssh_rsa(2048, $comment, $passphrase),
            'rsa4096' => generate_ssh_rsa(4096, $comment, $passphrase),
            default => null,
        };
        if ($result === null) {
            fail('algorithm must be ed25519, rsa2048, or rsa4096', 400, 'ssh');
        }

        if ($passphrase !== '' && function_exists('sodium_memzero')) {
            try {
                sodium_memzero($passphrase);
            } catch (Throwable) {
                $passphrase = '';
            }
        }

        ok('ssh', $result);
    }
}
