<?php
declare(strict_types=1);

namespace App\Controllers;

use Core\Http\Response;
use Core\View\View;
use Modules\Companies\Repositories\CompanyRepository;
use Modules\Settings\Repositories\SmtpSettingsRepository;

abstract class BaseController
{
    protected function renderPage(string $viewTemplate, array $data = [], string $layoutTemplate = 'layouts/main.php'): Response
    {
        // Templates stockés dans `app/views`.
        $viewsRoot = dirname(__DIR__, 1) . '/views';

        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $basePath = rtrim(str_replace('\\', '/', (string) dirname($scriptName)), '/');
        if ($basePath === '.' || $basePath === '\\') {
            $basePath = '';
        }

        $companyName = null;
        $companyLogoUrl = null;
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['company_id'])) {
            $sessionCid = (int) $_SESSION['company_id'];
            $cid = $sessionCid;
            if ($sessionCid > 0 && !empty($_SESSION['impersonate_target_company_id'])) {
                $target = (int) $_SESSION['impersonate_target_company_id'];
                $perms = isset($_SESSION['permissions']) && is_array($_SESSION['permissions']) ? $_SESSION['permissions'] : [];
                if ($target > 0 && in_array('platform.impersonate.start', $perms, true)) {
                    $cid = $target;
                }
            }
            if ($cid > 0) {
                try {
                    $co = (new CompanyRepository())->findById($cid);
                    if (is_array($co) && trim((string) ($co['name'] ?? '')) !== '') {
                        $companyName = (string) $co['name'];
                    }
                    $smtp = (new SmtpSettingsRepository())->getByCompanyId($cid);
                    $logoPath = trim((string) ($smtp['company_logo_path'] ?? ''));
                    if ($logoPath !== '') {
                        $companyLogoUrl = $basePath . $logoPath;
                    }
                } catch (\Throwable) {
                    // ignore branding errors
                }
            }
        }

        $impersonationBanner = null;
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['impersonate_target_company_id'])) {
            $target = (int) $_SESSION['impersonate_target_company_id'];
            $perms = isset($_SESSION['permissions']) && is_array($_SESSION['permissions']) ? $_SESSION['permissions'] : [];
            if ($target > 0 && in_array('platform.impersonate.start', $perms, true)) {
                try {
                    $tco = (new CompanyRepository())->findById($target);
                    $impersonationBanner = [
                        'companyId' => $target,
                        'companyName' => is_array($tco) ? (string) ($tco['name'] ?? ('#' . $target)) : ('#' . $target),
                    ];
                } catch (\Throwable) {
                    $impersonationBanner = ['companyId' => $target, 'companyName' => '#' . $target];
                }
            }
        }

        $dataWithBase = array_merge([
            'basePath' => $basePath,
            'companyName' => $companyName,
            'companyLogoUrl' => $companyLogoUrl,
            'impersonationBanner' => $impersonationBanner,
        ], $data);

        $contentHtml = View::render($viewsRoot . '/' . $viewTemplate, $dataWithBase);

        $html = View::render($viewsRoot . '/' . $layoutTemplate, [
            'basePath' => $basePath,
            'contentHtml' => $contentHtml,
            ...$dataWithBase,
        ]);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}

