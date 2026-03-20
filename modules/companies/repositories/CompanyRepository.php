<?php
declare(strict_types=1);

namespace Modules\Companies\Repositories;

use Core\Database\Connection;
use PDO;

final class CompanyRepository
{
    public function findById(int $companyId): ?array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id, name, billingEmail, status, billingPlan, billingStatus, maxSeats,
                   subscriptionRenewsAt, externalBillingRef
            FROM Company
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAll(int $limit = 500): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id, name, billingEmail, status, billingPlan, billingStatus, maxSeats,
                   subscriptionRenewsAt, externalBillingRef
            FROM Company
            ORDER BY id ASC
            LIMIT :limit
        ');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array{name:string, billingEmail:?string, status:string} $data
     */
    public function create(array $data): int
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            INSERT INTO Company (name, billingEmail, status)
            VALUES (:name, :billingEmail, :status)
        ');
        $stmt->execute([
            'name' => $data['name'],
            'billingEmail' => $data['billingEmail'] !== null && $data['billingEmail'] !== '' ? $data['billingEmail'] : null,
            'status' => $data['status'],
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * @param array{name:?string, billingEmail:?string, status:?string} $data
     */
    public function updateCore(int $companyId, array $data): void
    {
        $fields = [];
        $params = ['id' => $companyId];
        if (isset($data['name']) && $data['name'] !== null && $data['name'] !== '') {
            $fields[] = 'name = :name';
            $params['name'] = $data['name'];
        }
        if (array_key_exists('billingEmail', $data)) {
            $fields[] = 'billingEmail = :billingEmail';
            $params['billingEmail'] = $data['billingEmail'] !== null && $data['billingEmail'] !== '' ? $data['billingEmail'] : null;
        }
        if (isset($data['status']) && $data['status'] !== null && $data['status'] !== '') {
            $fields[] = 'status = :status';
            $params['status'] = $data['status'];
        }
        if ($fields === []) {
            return;
        }
        $fields[] = 'updatedAt = NOW()';
        $sql = 'UPDATE Company SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * @param array{
     *   billingPlan:?string,
     *   billingStatus:?string,
     *   maxSeats:?int,
     *   subscriptionRenewsAt:?string,
     *   externalBillingRef:?string
     * } $data
     */
    public function updateBilling(int $companyId, array $data): void
    {
        $fields = [];
        $params = ['id' => $companyId];
        if (array_key_exists('billingPlan', $data)) {
            $fields[] = 'billingPlan = :billingPlan';
            $params['billingPlan'] = $data['billingPlan'] !== null && $data['billingPlan'] !== '' ? $data['billingPlan'] : null;
        }
        if (array_key_exists('billingStatus', $data)) {
            $fields[] = 'billingStatus = :billingStatus';
            $params['billingStatus'] = $data['billingStatus'] !== null && $data['billingStatus'] !== '' ? $data['billingStatus'] : null;
        }
        if (array_key_exists('maxSeats', $data)) {
            $fields[] = 'maxSeats = :maxSeats';
            $params['maxSeats'] = $data['maxSeats'];
        }
        if (array_key_exists('subscriptionRenewsAt', $data)) {
            $fields[] = 'subscriptionRenewsAt = :subscriptionRenewsAt';
            $params['subscriptionRenewsAt'] = $data['subscriptionRenewsAt'] !== null && $data['subscriptionRenewsAt'] !== '' ? $data['subscriptionRenewsAt'] : null;
        }
        if (array_key_exists('externalBillingRef', $data)) {
            $fields[] = 'externalBillingRef = :externalBillingRef';
            $params['externalBillingRef'] = $data['externalBillingRef'] !== null && $data['externalBillingRef'] !== '' ? $data['externalBillingRef'] : null;
        }
        if ($fields === []) {
            return;
        }
        $fields[] = 'updatedAt = NOW()';
        $sql = 'UPDATE Company SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function updateStatus(int $companyId, string $status): void
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE Company SET status = :status, updatedAt = NOW() WHERE id = :id
        ');
        $stmt->execute(['status' => $status, 'id' => $companyId]);
    }
}
