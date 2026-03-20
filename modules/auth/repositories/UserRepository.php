<?php
declare(strict_types=1);

namespace Modules\Auth\Repositories;

use Core\Database\Connection;
use PDO;

final class UserRepository
{
    public function findActiveByEmail(string $email): ?array
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            SELECT id, companyId, email, passwordHash, status
            FROM `User`
            WHERE email = :email
              AND status = "active"
            LIMIT 1
        ');
        $stmt->execute(['email' => $email]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $userId): ?array
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            SELECT id, companyId, email, fullName, phone, status
            FROM `User`
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

