<?php
declare(strict_types=1);

namespace Core\Database;

use Core\Config;
use PDO;

final class Connection
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $pdo = new PDO(
            dsn: Config::dbDsn(),
            username: Config::dbUser(),
            password: Config::dbPassword(),
            options: [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );

        self::$pdo = $pdo;
        return self::$pdo;
    }
}

