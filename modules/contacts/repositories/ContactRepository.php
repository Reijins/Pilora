<?php
declare(strict_types=1);

namespace Modules\Contacts\Repositories;

use Core\Database\Connection;
use PDO;

final class ContactRepository
{
    /**
     * @return array<int, array{id:int, clientId:int, firstName:?string, lastName:?string, functionLabel:?string, email:?string, phone:?string}>
     */
    public function listByCompanyId(int $companyId, int $limit = 500): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id, clientId, firstName, lastName, functionLabel, email, phone
            FROM Contact
            WHERE companyId = :companyId
            ORDER BY id DESC
            LIMIT :limit
        ');
        $stmt->bindValue('companyId', $companyId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int, array{id:int, firstName:?string, lastName:?string, functionLabel:?string, email:?string, phone:?string}>
     */
    public function listByCompanyIdAndClientId(int $companyId, int $clientId): array
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            SELECT id, firstName, lastName, functionLabel, email, phone
            FROM Contact
            WHERE companyId = :companyId AND clientId = :clientId
            ORDER BY id DESC
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'clientId' => $clientId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }

    public function create(
        int $companyId,
        int $clientId,
        ?string $firstName,
        ?string $lastName,
        ?string $functionLabel,
        ?string $email,
        ?string $phone,
        ?string $notes
    ): int {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare('
            INSERT INTO Contact (
                companyId,
                clientId,
                firstName,
                lastName,
                functionLabel,
                email,
                phone,
                notes,
                createdAt,
                updatedAt
            ) VALUES (
                :companyId,
                :clientId,
                :firstName,
                :lastName,
                :functionLabel,
                :email,
                :phone,
                :notes,
                NOW(),
                NOW()
            )
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'clientId' => $clientId,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'functionLabel' => $functionLabel,
            'email' => $email,
            'phone' => $phone,
            'notes' => $notes,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function findByCompanyIdAndId(int $companyId, int $contactId): ?array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id, clientId, firstName, lastName, functionLabel, email, phone, notes
            FROM Contact
            WHERE companyId = :companyId AND id = :id
            LIMIT 1
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'id' => $contactId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function update(
        int $companyId,
        int $contactId,
        int $clientId,
        ?string $firstName,
        ?string $lastName,
        ?string $functionLabel,
        ?string $email,
        ?string $phone
    ): bool {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            UPDATE Contact
            SET clientId = :clientId,
                firstName = :firstName,
                lastName = :lastName,
                functionLabel = :functionLabel,
                email = :email,
                phone = :phone,
                updatedAt = NOW()
            WHERE companyId = :companyId
              AND id = :contactId
        ');
        $stmt->execute([
            'clientId' => $clientId,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'functionLabel' => $functionLabel,
            'email' => $email,
            'phone' => $phone,
            'companyId' => $companyId,
            'contactId' => $contactId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $companyId, int $contactId): bool
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('DELETE FROM Contact WHERE companyId = :companyId AND id = :contactId');
        $stmt->execute([
            'companyId' => $companyId,
            'contactId' => $contactId,
        ]);
        return $stmt->rowCount() > 0;
    }
}

