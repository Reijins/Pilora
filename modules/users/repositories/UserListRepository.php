<?php
declare(strict_types=1);

namespace Modules\Users\Repositories;

use Core\Database\Connection;
use PDO;

final class UserListRepository
{
    /**
     * @return array<int, array{id:int, fullName:?string, email:string, status:string}>
     */
    public function listByCompanyId(int $companyId, int $limit = 200): array
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            SELECT id, email, fullName, status
            FROM `User`
            WHERE companyId = :companyId
            ORDER BY id DESC
            LIMIT :limit
        ');
        $stmt->bindValue('companyId', $companyId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

