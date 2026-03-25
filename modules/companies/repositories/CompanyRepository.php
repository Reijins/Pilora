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
            SELECT id, name, workHoursPerDay, companyKind, billingEmail, status, billingPlan, billingStatus, billingCycle, maxSeats,
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
            SELECT id, name, workHoursPerDay, companyKind, billingEmail, status, billingPlan, billingStatus, billingCycle, maxSeats,
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
     * Sociétés clientes (tenants) — exclut la société interne back-office plateforme.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listTenantCompanies(int $limit = 500): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id, name, workHoursPerDay, companyKind, billingEmail, status, billingPlan, billingStatus, billingCycle, maxSeats,
                   subscriptionRenewsAt, externalBillingRef
            FROM Company
            WHERE companyKind = :tenant
            ORDER BY id ASC
            LIMIT :limit
        ');
        $stmt->bindValue('tenant', 'tenant', PDO::PARAM_STR);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Société interne réservée aux comptes back-office (utilisateurs plateforme).
     */
    public function ensurePlatformOperatorCompany(): int
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('SELECT id FROM Company WHERE companyKind = :k LIMIT 1');
        $stmt->execute(['k' => 'platform']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $id = (int) $row['id'];
            $this->syncPlatformRolePermissionsForCompany($id);
            return $id;
        }

        $pdo->prepare('
            INSERT INTO Company (name, status, companyKind, maxSeats)
            VALUES ("Back-office (interne)", "active", "platform", NULL)
        ')->execute();
        $id = (int) $pdo->lastInsertId();
        $this->syncPlatformRolePermissionsForCompany($id);
        return $id;
    }

    /**
     * Copie les liaisons RolePermission du rôle plateforme depuis une société déjà configurée (ex. seed).
     */
    public function syncPlatformRolePermissionsForCompany(int $targetCompanyId): void
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT rp.companyId AS cid
            FROM RolePermission rp
            INNER JOIN Role r ON r.id = rp.roleId AND r.scope = "platform"
            WHERE rp.companyId <> :tid
            LIMIT 1
        ');
        $stmt->execute(['tid' => $targetCompanyId]);
        $srcRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $sourceCompanyId = $srcRow ? (int) $srcRow['cid'] : 0;

        if ($sourceCompanyId > 0 && $sourceCompanyId !== $targetCompanyId) {
            $ins = $pdo->prepare('
                INSERT IGNORE INTO RolePermission (companyId, roleId, permissionId, createdAt)
                SELECT :tid, roleId, permissionId, NOW()
                FROM RolePermission
                WHERE companyId = :sid
                  AND roleId IN (SELECT id FROM Role WHERE scope = "platform")
            ');
            $ins->execute(['tid' => $targetCompanyId, 'sid' => $sourceCompanyId]);
        }

        // Fallback si aucune source (base neuve) : lier toutes les permissions plateforme au rôle opérateur
        $check = $pdo->prepare('
            SELECT COUNT(*) AS c FROM RolePermission rp
            INNER JOIN Role r ON r.id = rp.roleId AND r.scope = "platform"
            WHERE rp.companyId = :tid
        ');
        $check->execute(['tid' => $targetCompanyId]);
        $cnt = (int) ($check->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
        if ($cnt > 0) {
            return;
        }

        $roleStmt = $pdo->query('SELECT id FROM Role WHERE scope = "platform" AND code = "platform_operator" LIMIT 1');
        $roleRow = $roleStmt ? $roleStmt->fetch(PDO::FETCH_ASSOC) : false;
        $roleId = $roleRow ? (int) $roleRow['id'] : 0;
        if ($roleId <= 0) {
            return;
        }
        $permStmt = $pdo->query('SELECT id FROM Permission WHERE scope = "platform" AND companyId IS NULL');
        $permRows = $permStmt ? $permStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $insert = $pdo->prepare('
            INSERT IGNORE INTO RolePermission (companyId, roleId, permissionId, createdAt)
            VALUES (:companyId, :roleId, :permissionId, NOW())
        ');
        foreach ($permRows as $pr) {
            $pid = (int) ($pr['id'] ?? 0);
            if ($pid > 0) {
                $insert->execute([
                    'companyId' => $targetCompanyId,
                    'roleId' => $roleId,
                    'permissionId' => $pid,
                ]);
            }
        }
    }

    /**
     * Identité « Entreprise » sur devis / factures PDF (société tenant + paramètres d’envoi).
     *
     * @param array<string, mixed> $smtp Données SMTP société (from_name, from_email).
     * @return array{name:string, email:string, billing_email:string}
     */
    public function getDocumentIdentity(int $companyId, array $smtp): array
    {
        $row = $this->findById($companyId) ?? [];
        $name = trim((string) ($row['name'] ?? '')) !== ''
            ? (string) $row['name']
            : (trim((string) ($smtp['from_name'] ?? '')) !== '' ? (string) $smtp['from_name'] : 'Entreprise');
        $fromMail = trim((string) ($smtp['from_email'] ?? ''));
        $billing = trim((string) ($row['billingEmail'] ?? ''));
        $email = $fromMail !== '' ? $fromMail : $billing;

        return [
            'name' => $name,
            'email' => $email,
            'billing_email' => $billing,
        ];
    }

    /**
     * @param array{name:string, billingEmail:?string, status:string} $data
     */
    public function create(array $data): int
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            INSERT INTO Company (name, billingEmail, status, companyKind)
            VALUES (:name, :billingEmail, :status, "tenant")
        ');
        $stmt->execute([
            'name' => $data['name'],
            'billingEmail' => $data['billingEmail'] !== null && $data['billingEmail'] !== '' ? $data['billingEmail'] : null,
            'status' => $data['status'],
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * @param array{name:?string, billingEmail:?string, status:?string, workHoursPerDay:?float} $data
     */
    public function updateCore(int $companyId, array $data): void
    {
        $fields = [];
        $params = ['id' => $companyId];
        if (isset($data['name']) && $data['name'] !== null && $data['name'] !== '') {
            $fields[] = 'name = :name';
            $params['name'] = $data['name'];
        }
        if (array_key_exists('workHoursPerDay', $data) && $data['workHoursPerDay'] !== null) {
            $wh = max(0.01, min(24.0, (float) $data['workHoursPerDay']));
            $fields[] = 'workHoursPerDay = :workHoursPerDay';
            $params['workHoursPerDay'] = round($wh, 2);
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
     *   billingCycle:?string,
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
        if (array_key_exists('billingCycle', $data)) {
            $fields[] = 'billingCycle = :billingCycle';
            $params['billingCycle'] = $data['billingCycle'] !== null && $data['billingCycle'] !== '' ? $data['billingCycle'] : null;
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
