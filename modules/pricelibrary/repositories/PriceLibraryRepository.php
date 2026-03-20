<?php
declare(strict_types=1);

namespace Modules\PriceLibrary\Repositories;

use Core\Database\Connection;
use PDO;

final class PriceLibraryRepository
{
    public function listByCompanyId(int $companyId, bool $onlyActive = false, int $limit = 300): array
    {
        $pdo = Connection::pdo();

        $sql = '
            SELECT id, code, name, description, unitLabel, unitPrice, estimatedTimeMinutes, status
            FROM PriceLibraryItem
            WHERE companyId = :companyId
        ';
        if ($onlyActive) {
            $sql .= ' AND status = "active" ';
        }
        $sql .= ' ORDER BY id DESC LIMIT :limit';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('companyId', $companyId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(
        int $companyId,
        ?string $code,
        string $name,
        ?string $description,
        ?string $unitLabel,
        float $unitPrice,
        ?int $estimatedTimeMinutes,
        string $status = 'active'
    ): int {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            INSERT INTO PriceLibraryItem (
                companyId, code, name, description, unitLabel, unitPrice, estimatedTimeMinutes, status, createdAt, updatedAt
            ) VALUES (
                :companyId, :code, :name, :description, :unitLabel, :unitPrice, :estimatedTimeMinutes, :status, NOW(), NOW()
            )
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'code' => $code !== '' ? $code : null,
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'unitLabel' => $unitLabel !== '' ? $unitLabel : null,
            'unitPrice' => round($unitPrice, 2),
            'estimatedTimeMinutes' => $estimatedTimeMinutes,
            'status' => $status === 'inactive' ? 'inactive' : 'active',
        ]);

        return (int) $pdo->lastInsertId();
    }
}

