<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Catalog;
use App\Ui;
use App\View;

final class PageController
{
    public static function dispatch(): never
    {
        require_once APP_ROOT . '/app/ViewHelpers.php';

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $normalizedPath = rtrim($path, '/') ?: '/';

        if (
            $normalizedPath === '/health'
            || (
                ($_GET['__route'] ?? '') === 'health'
                && ($normalizedPath === '/' || $normalizedPath === '/index.php')
            )
        ) {
            health_response();
        }

        if ($path === '/api.php') {
            require APP_ROOT . '/api.php';
            exit;
        }

        $page = Catalog::pageForPath($normalizedPath);
        if ($page === null) {
            render_error_page(
                404,
                'Page not found',
                'The tool or page you requested does not exist. Check the URL or return to the tools index.'
            );
        }

        $tools = Catalog::tools();
        $security = app_security();
        $currentTool = Catalog::tool($page);
        $toolName = $currentTool[1] ?? ucwords(str_replace('-', ' ', (string) $page));
        $toolDesc = $currentTool[2] ?? 'Fast, private developer utilities';
        $siteUrl = 'https://tools.rajujha.dev';
        $canonical = $page === 'home' ? $siteUrl . '/' : $siteUrl . '/' . $page;
        $title = $page === 'home' ? 'Developer Tools' : $toolName . ' · Developer Tools';
        $description = $page === 'home'
            ? 'Fast, private developer utilities. Passwords, hashes, JSON, JWT, Markdown, encryption and more — mostly in your browser, with optional JSON APIs.'
            : $toolDesc . ' Runs locally in your browser unless you explicitly use the optional API.';

        $ui = Ui::classes();
        $view = [
            'page' => $page,
            'tools' => $tools,
            'currentTool' => $currentTool,
            'toolName' => $toolName,
            'toolDesc' => $toolDesc,
            'canonical' => $canonical,
            'title' => $title,
            'description' => $description,
            'cssVersion' => (string) @filemtime(APP_ROOT . '/assets/app.css'),
            'jsVersion' => (string) @filemtime(APP_ROOT . '/assets/app.js'),
            'themeJsVersion' => (string) @filemtime(APP_ROOT . '/assets/theme.js'),
            'bcryptCost' => $security['bcrypt_cost'],
            'maxBcryptCost' => $security['max_bcrypt_cost'],
            'encIter' => $security['encryption_iterations'],
            'maxEncIter' => $security['max_encryption_iterations'],
            'meta' => Catalog::meta(),
            'examples' => Catalog::examples(),
            'field' => $ui['field'],
            'controlSelect' => $ui['controlSelect'],
            'btn' => $ui['btn'],
            'btnPrimary' => $ui['btnPrimary'],
            'chip' => $ui['chip'],
            'label' => $ui['label'],
            'hint' => $ui['hint'],
            'result' => $ui['result'],
            'resultBody' => $ui['resultBody'],
            'iconBtn' => $ui['iconBtn'],
            'iconSvg' => $ui['iconSvg'],
            'stat' => $ui['stat'],
            'check' => $ui['check'],
            'controlRow' => $ui['controlRow'],
        ];

        View::render('layout', $view);
        exit;
    }
}
