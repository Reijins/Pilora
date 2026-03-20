<?php
declare(strict_types=1);

namespace Core\View;

final class View
{
    public static function render(string $templatePath, array $data = []): string
    {
        if (!is_file($templatePath)) {
            throw new \RuntimeException('Template introuvable: ' . $templatePath);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $templatePath;
        return (string) ob_get_clean();
    }
}

