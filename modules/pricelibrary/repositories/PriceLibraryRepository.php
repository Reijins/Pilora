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
            SELECT
                i.id,
                i.code,
                i.name,
                i.description,
                i.unitLabel,
                i.unitPrice,
                i.defaultVatRate,
                i.defaultRevenueAccount,
                i.categoryId,
                i.estimatedTimeMinutes,
                i.status,
                c.name AS categoryName,
                c.defaultVatRate AS categoryDefaultVatRate,
                c.defaultRevenueAccount AS categoryDefaultRevenueAccount
            FROM PriceLibraryItem i
            LEFT JOIN PriceCategory c
                ON c.id = i.categoryId
               AND c.companyId = i.companyId
            WHERE i.companyId = :companyId
        ';
        if ($onlyActive) {
            $sql .= ' AND i.status = "active" ';
        }
        $sql .= ' ORDER BY i.id DESC LIMIT :limit';

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
        ?int $categoryId,
        ?float $defaultVatRate,
        ?string $defaultRevenueAccount,
        ?int $estimatedTimeMinutes,
        string $status = 'active'
    ): int {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            INSERT INTO PriceLibraryItem (
                companyId, code, name, description, unitLabel, unitPrice, categoryId, defaultVatRate, defaultRevenueAccount,
                estimatedTimeMinutes, status, createdAt, updatedAt
            ) VALUES (
                :companyId, :code, :name, :description, :unitLabel, :unitPrice, :categoryId, :defaultVatRate, :defaultRevenueAccount,
                :estimatedTimeMinutes, :status, NOW(), NOW()
            )
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'code' => $code !== '' ? $code : null,
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'unitLabel' => $unitLabel !== '' ? $unitLabel : null,
            'unitPrice' => round($unitPrice, 2),
            'categoryId' => $categoryId !== null && $categoryId > 0 ? $categoryId : null,
            'defaultVatRate' => $defaultVatRate !== null ? round($defaultVatRate, 2) : null,
            'defaultRevenueAccount' => $defaultRevenueAccount !== null && trim($defaultRevenueAccount) !== '' ? trim($defaultRevenueAccount) : null,
            'estimatedTimeMinutes' => $estimatedTimeMinutes,
            'status' => $status === 'inactive' ? 'inactive' : 'active',
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function findByCompanyAndId(int $companyId, int $id): ?array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT
                i.id,
                i.companyId,
                i.code,
                i.name,
                i.description,
                i.unitLabel,
                i.unitPrice,
                i.categoryId,
                i.defaultVatRate,
                i.defaultRevenueAccount,
                i.estimatedTimeMinutes,
                i.status,
                c.name AS categoryName,
                c.defaultVatRate AS categoryDefaultVatRate,
                c.defaultRevenueAccount AS categoryDefaultRevenueAccount
            FROM PriceLibraryItem i
            LEFT JOIN PriceCategory c
                ON c.id = i.categoryId
               AND c.companyId = i.companyId
            WHERE i.companyId = :companyId AND i.id = :id
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
     *   categoryId:?int,
     *   defaultVatRate:?float|"",
     *   defaultRevenueAccount:?string,
     *   estimatedTimeMinutes:?int,
     *   status:string
     * } $data
     */
    public function updateByCompanyAndId(int $companyId, int $id, array $data): void
    {
        $pdo = Connection::pdo();
        $defVatSql = null;
        if (\array_key_exists('defaultVatRate', $data)) {
            $rawVat = $data['defaultVatRate'];
            if ($rawVat === null || $rawVat === '') {
                $defVatSql = null;
            } elseif (is_numeric($rawVat)) {
                $defVatSql = round((float) $rawVat, 2);
            }
        }
        $defAcc = isset($data['defaultRevenueAccount']) ? trim((string) $data['defaultRevenueAccount']) : '';
        $defAccSql = $defAcc !== '' ? $defAcc : null;

        $stmt = $pdo->prepare('
            UPDATE PriceLibraryItem SET
                name = :name,
                description = :description,
                unitLabel = :unitLabel,
                unitPrice = :unitPrice,
                categoryId = :categoryId,
                defaultVatRate = :defaultVatRate,
                defaultRevenueAccount = :defaultRevenueAccount,
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
            'categoryId' => isset($data['categoryId']) && is_numeric($data['categoryId']) && (int) $data['categoryId'] > 0
                ? (int) $data['categoryId']
                : null,
            'defaultVatRate' => $defVatSql,
            'defaultRevenueAccount' => $defAccSql,
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

