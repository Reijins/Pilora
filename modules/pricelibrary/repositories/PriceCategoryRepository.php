<?php
declare(strict_types=1);

namespace Modules\PriceLibrary\Repositories;

use Core\Database\Connection;
use PDO;

final class PriceCategoryRepository
{
    public function listByCompanyId(int $companyId, bool $onlyActive = false, int $limit = 300): array
    {
        $pdo = Connection::pdo();
        $sql = '
            SELECT id, companyId, name, defaultVatRate, defaultRevenueAccount, status
            FROM PriceCategory
            WHERE companyId = :companyId
        ';
        if ($onlyActive) {
            $sql .= ' AND status = "active" ';
        }
        $sql .= ' ORDER BY name ASC, id DESC LIMIT :limit';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('companyId', $companyId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByCompanyAndId(int $companyId, int $id): ?array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id, companyId, name, defaultVatRate, defaultRevenueAccount, status
            FROM PriceCategory
            WHERE companyId = :companyId AND id = :id
            LIMIT 1
        ');
        $stmt->execute(['companyId' => $companyId, 'id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(
        int $companyId,
        string $name,
        ?float $defaultVatRate,
        ?string $defaultRevenueAccount,
        string $status = 'active'
    ): int {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            INSERT INTO PriceCategory (
                companyId, name, defaultVatRate, defaultRevenueAccount, status, createdAt, updatedAt
            ) VALUES (
                :companyId, :name, :defaultVatRate, :defaultRevenueAccount, :status, NOW(), NOW()
            )
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'name' => trim($name),
            'defaultVatRate' => $defaultVatRate !== null ? round($defaultVatRate, 2) : null,
            'defaultRevenueAccount' => $defaultRevenueAccount !== null && trim($defaultRevenueAccount) !== ''
                ? trim($defaultRevenueAccount)
                : null,
            'status' => $status === 'inactive' ? 'inactive' : 'active',
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @param array{name:string,defaultVatRate:?float|"",defaultRevenueAccount:?string,status:string} $data
     */
    public function updateByCompanyAndId(int $companyId, int $id, array $data): void
    {
        $pdo = Connection::pdo();
        $rawVat = $data['defaultVatRate'] ?? null;
        $defVatSql = null;
        if ($rawVat !== null && $rawVat !== '' && is_numeric($rawVat)) {
            $defVatSql = round((float) $rawVat, 2);
        }
        $defAcc = isset($data['defaultRevenueAccount']) ? trim((string) $data['defaultRevenueAccount']) : '';
        $defAccSql = $defAcc !== '' ? $defAcc : null;

        $stmt = $pdo->prepare('
            UPDATE PriceCategory SET
                name = :name,
                defaultVatRate = :defaultVatRate,
                defaultRevenueAccount = :defaultRevenueAccount,
                status = :status,
                updatedAt = NOW()
            WHERE companyId = :companyId AND id = :id
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'id' => $id,
            'name' => trim((string) ($data['name'] ?? '')),
            'defaultVatRate' => $defVatSql,
            'defaultRevenueAccount' => $defAccSql,
            'status' => ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
        ]);
    }
}

