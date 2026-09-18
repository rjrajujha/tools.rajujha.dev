<?php
declare(strict_types=1);

namespace App;

final class View
{
    public static function render(string $template, array $data = []): void
    {
        $file = self::path($template);
        if (!is_file($file)) {
            render_error_page(500, 'Server error', 'The requested view is not available.');
        }

        extract($data, EXTR_SKIP);
        require $file;
    }

    private static function path(string $template): string
    {
        $template = str_replace('\\', '/', $template);
        if (
            $template === ''
            || str_contains($template, '..')
            || str_contains($template, "\0")
            || !preg_match('/^[a-zA-Z0-9_\/-]+$/', $template)
        ) {
            render_error_page(500, 'Server error', 'The requested view is not available.');
        }

        return APP_ROOT . '/app/Views/' . $template . '.php';
    }
}
