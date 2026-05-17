<?php
declare(strict_types=1);

namespace Modules\Marketing\Services;

final class MarketingBrandService
{
    public const LOGO_RELATIVE_PATH = '/public/assets/pilora-logo.png';

    /**
     * Logo Pilora (même fichier que la topbar back-office).
     */
    public function brandLogoUrl(string $basePath): ?string
    {
        $basePath = rtrim($basePath, '/');
        $appRoot = dirname(__DIR__, 3);
        $relative = self::LOGO_RELATIVE_PATH;

        if (!is_file($appRoot . $relative)) {
            return null;
        }

        return $basePath . $relative;
    }
}
