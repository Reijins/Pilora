<?php
declare(strict_types=1);

namespace Core;

final class Autoloader
{
    public function register(): void
    {
        spl_autoload_register([$this, 'autoload'], true, true);
    }

    private function autoload(string $class): void
    {
        $prefixes = [
            'Core\\' => __DIR__ . '/',
            'App\\' => dirname(__DIR__) . '/app/',
            'Modules\\' => dirname(__DIR__) . '/modules/',
        ];

        foreach ($prefixes as $prefix => $baseDir) {
            if (str_starts_with($class, $prefix)) {
                $relative = substr($class, strlen($prefix));
                $path = $baseDir . str_replace('\\', '/', $relative) . '.php';

                if (is_file($path)) {
                    require $path;
                }
                return;
            }
        }
    }
}

