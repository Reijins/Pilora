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

    public function findByCompanyAndId(int $companyId, int $id): ?array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id, companyId, code, name, description, unitLabel, unitPrice, estimatedTimeMinutes, status
            FROM PriceLibraryItem
            WHERE companyId = :companyId AND id = :id
            LIMIT 1
        ');
        $stmt->execute(['companyId' => $companyId, 'id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @param array{
     *   name:string,
     *   description:?string,
     *   unitLabel:?string,
     *   unitPrice:float,
     *   estimatedTimeMinutes:?int,
     *   status:string
     * } $data
     */
    public function updateByCompanyAndId(int $companyId, int $id, array $data): void
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE PriceLibraryItem SET
                name = :name,
                description = :description,
                unitLabel = :unitLabel,
                unitPrice = :unitPrice,
                estimatedTimeMinutes = :estimatedTimeMinutes,
                status = :status,
                updatedAt = NOW()
            WHERE companyId = :companyId AND id = :id
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'id' => $id,
            'name' => $data['name'],
            'description' => $data['description'],
            'unitLabel' => $data['unitLabel'],
            'unitPrice' => round($data['unitPrice'], 2),
            'estimatedTimeMinutes' => $data['estimatedTimeMinutes'],
            'status' => $data['status'] === 'inactive' ? 'inactive' : 'active',
        ]);
    }

    public function deleteByCompanyAndId(int $companyId, int $id): bool
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            DELETE FROM PriceLibraryItem
            WHERE companyId = :companyId AND id = :id AND status = "inactive"
        ');
        $stmt->execute(['companyId' => $companyId, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }
}

