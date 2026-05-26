<?php
declare(strict_types=1);

namespace Modules\Clients\Repositories;

use Core\Database\Connection;
use PDO;

final class ClientRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchByCompanyId(int $companyId, ?string $query, int $limit = 50): array
    {
        $pdo = Connection::pdo();

        $sql = '
            SELECT c.id, c.clientNumber, c.name, c.status, c.isBillable, c.accountingCustomerAccount,
                   pc.phone AS contactPhone, pc.email AS contactEmail,
                   pc.firstName AS contactFirstName, pc.lastName AS contactLastName
            FROM Client c
            LEFT JOIN Contact pc ON pc.companyId = c.companyId AND pc.clientId = c.id AND pc.isPrimaryContact = 1
            WHERE c.companyId = :companyId
              AND c.status = "active"
        ';

        $params = ['companyId' => $companyId];

        $query = $query !== null ? trim($query) : null;
        if ($query !== null && $query !== '') {
            $sql .= '
                AND (
                    c.name LIKE :q
                    OR pc.phone LIKE :q
                    OR pc.email LIKE :q
                    OR pc.firstName LIKE :q
                    OR pc.lastName LIKE :q
                )
            ';
            $params['q'] = '%' . $query . '%';
        }

        $sql .= ' ORDER BY c.id DESC LIMIT :limit';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('companyId', $params['companyId'], PDO::PARAM_INT);
        if (isset($params['q'])) {
            $stmt->bindValue('q', $params['q'], PDO::PARAM_STR);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByCompanyIdAndId(int $companyId, int $clientId): ?array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id, clientNumber, name, phone, email, address, notes, siret, accountingCustomerAccount, status, isBillable
            FROM Client
            WHERE companyId = :companyId AND id = :id
            LIMIT 1
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'id' => $clientId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $siretCol = trim((string) ($row['siret'] ?? ''));
        if ($siretCol === '' && preg_match('/\[SIRET:([0-9]+)\]/', (string) ($row['notes'] ?? ''), $m)) {
            $row['siret'] = $m[1];
        }

        return $row;
    }

    public function createClient(
        int $companyId,
        string $name,
        ?string $phone,
        ?string $email,
        ?string $address,
        ?string $notes,
        ?string $siret = null,
        ?string $accountingCustomerAccount = null,
    ): int {
        $pdo = Connection::pdo();

        $clientNumber = $this->nextClientNumber($companyId);

        $stmt = $pdo->prepare('
            INSERT INTO Client (companyId, clientNumber, name, phone, email, address, notes, siret, accountingCustomerAccount, createdAt, updatedAt)
            VALUES (:companyId, :clientNumber, :name, :phone, :email, :address, :notes, :siret, :accountingCustomerAccount, NOW(), NOW())
        ');

        $stmt->execute([
            'companyId' => $companyId,
            'clientNumber' => $clientNumber,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'notes' => $notes,
            'siret' => $siret !== null && $siret !== '' ? $siret : null,
            'accountingCustomerAccount' => $accountingCustomerAccount !== null && trim($accountingCustomerAccount) !== '' ? trim($accountingCustomerAccount) : null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    private function nextClientNumber(int $companyId): string
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT clientNumber FROM Client
            WHERE companyId = :companyId AND clientNumber IS NOT NULL
            ORDER BY clientNumber DESC LIMIT 1
        ');
        $stmt->execute(['companyId' => $companyId]);
        $last = $stmt->fetchColumn();

        $seq = 1;
        if (is_string($last) && preg_match('/^C(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return 'C' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    public function updateClient(
        int $companyId,
        int $clientId,
        string $name,
        ?string $phone,
        ?string $email,
        ?string $address,
        ?string $notes,
        ?string $siret = null,
        ?string $accountingCustomerAccount = null,
    ): bool {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE Client
            SET name = :name,
                phone = :phone,
                email = :email,
                address = :address,
                notes = :notes,
                siret = :siret,
                accountingCustomerAccount = :accountingCustomerAccount,
                updatedAt = NOW()
            WHERE companyId = :companyId
              AND id = :clientId
        ');
        $stmt->execute([
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'notes' => $notes,
            'siret' => $siret !== null && $siret !== '' ? $siret : null,
            'accountingCustomerAccount' => $accountingCustomerAccount !== null && trim($accountingCustomerAccount) !== '' ? trim($accountingCustomerAccount) : null,
            'companyId' => $companyId,
            'clientId' => $clientId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function softDelete(int $companyId, int $clientId): bool
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE Client SET status = "deleted", updatedAt = NOW()
            WHERE companyId = :companyId AND id = :clientId AND status = "active"
        ');
        $stmt->execute(['companyId' => $companyId, 'clientId' => $clientId]);

        return $stmt->rowCount() > 0;
    }

    public function setBillable(int $companyId, int $clientId, bool $billable): bool
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE Client SET isBillable = :val, updatedAt = NOW()
            WHERE companyId = :companyId AND id = :clientId
        ');
        $stmt->execute([
            'val' => $billable ? 1 : 0,
            'companyId' => $companyId,
            'clientId' => $clientId,
        ]);

        return $stmt->rowCount() > 0;
    }
}

