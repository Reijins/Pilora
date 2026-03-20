<?php
declare(strict_types=1);

namespace Core;

use Dotenv\Dotenv;

final class Config
{
    private static bool $loaded = false;

    public static function env(string $key, ?string $default = null): ?string
    {
        if (!self::$loaded) {
            self::loadEnv();
        }

        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    private static function loadEnv(): void
    {
        $root = dirname(__DIR__);
        $envPath = $root . '/.env';
        if (!is_file($envPath)) {
            self::$loaded = true;
            return;
        }

        $dotenv = Dotenv::createImmutable($root);
        $dotenv->safeLoad();
        self::$loaded = true;
    }

    public static function dbDsn(): string
    {
        $host = (string) (self::env('DB_HOST', '127.0.0.1'));
        $port = (string) (self::env('DB_PORT', '3306'));
        $dbName = (string) (self::env('DB_NAME', 'pilora'));
        $charset = (string) (self::env('DB_CHARSET', 'utf8mb4'));

        return sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $dbName, $charset);
    }

    public static function dbUser(): string
    {
        return (string) (self::env('DB_USER', 'root'));
    }

    public static function dbPassword(): string
    {
        return (string) (self::env('DB_PASSWORD', ''));
    }

    public static function sessionName(): string
    {
        return (string) (self::env('SESSION_NAME', 'pilora_session'));
    }

    public static function sessionLifetimeSeconds(): int
    {
        return (int) (self::env('SESSION_LIFETIME_SECONDS', '1200'));
    }

    public static function sessionInactivityTimeoutSeconds(): int
    {
        return (int) (self::env('SESSION_INACTIVITY_TIMEOUT_SECONDS', '300'));
    }

    public static function isDebug(): bool
    {
        return strtolower((string) (self::env('LOG_LEVEL', 'debug'))) !== 'info';
    }
}

