<?php
declare(strict_types=1);

namespace Modules\Clients\Repositories;

use Core\Database\Connection;
use PDO;

final class ClientRepository
{
    /**
     * @return array<int, array{id:int, name:string, phone:?string, email:?string}>
     */
    public function searchByCompanyId(int $companyId, ?string $query, int $limit = 50): array
    {
        $pdo = Connection::pdo();

        $sql = '
            SELECT id, name, phone, email, accountingCustomerAccount
            FROM Client
            WHERE companyId = :companyId
        ';

        $params = ['companyId' => $companyId];

        $query = $query !== null ? trim($query) : null;
        if ($query !== null && $query !== '') {
            $sql .= '
                AND (
                    name LIKE :q
                    OR phone LIKE :q
                    OR email LIKE :q
                )
            ';
            $params['q'] = '%' . $query . '%';
        }

        $sql .= ' ORDER BY id DESC LIMIT :limit';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('companyId', $params['companyId'], PDO::PARAM_INT);
        if (isset($params['q'])) {
            $stmt->bindValue('q', $params['q'], PDO::PARAM_STR);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByCompanyIdAndId(int $companyId, int $clientId): ?array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id, name, phone, email, address, notes, siret, accountingCustomerAccount
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

        $stmt = $pdo->prepare('
            INSERT INTO Client (companyId, name, phone, email, address, notes, siret, accountingCustomerAccount, createdAt, updatedAt)
            VALUES (:companyId, :name, :phone, :email, :address, :notes, :siret, :accountingCustomerAccount, NOW(), NOW())
        ');

        $stmt->execute([
            'companyId' => $companyId,
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
}

