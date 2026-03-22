<?php
declare(strict_types=1);

namespace Modules\Rbac\Services;

use Core\Database\Connection;
use PDO;

/**
 * Initialise les permissions / rôles tenant pour une société (équivalent logique à seed_dev + scripts d’extension).
 */
final class TenantRbacBootstrapService
{
    /**
     * @return array<string, string> code => description
     */
    public static function permissionCatalog(): array
    {
        return [
            'client.read' => 'Lire les clients',
            'client.create' => 'Créer des clients',
            'client.update' => 'Mettre à jour les clients',
            'client.delete' => 'Supprimer des clients',
            'quote.read' => 'Lire les devis',
            'quote.create' => 'Créer des devis',
            'quote.send' => 'Envoyer des devis',
            'quote.followup' => 'Gérer les relances devis',
            'invoice.read' => 'Lire les factures',
            'invoice.create' => 'Créer des factures',
            'invoice.update' => 'Mettre à jour les factures',
            'invoice.mark_paid' => 'Marquer une facture payée',
            'invoice.export' => 'Exporter en CSV pour comptabilité',
            'project.read' => 'Lire les chantiers',
            'project.create' => 'Créer des chantiers',
            'project.update' => 'Mettre à jour les chantiers',
            'hr.leave.request' => 'Demander un congé',
            'hr.leave.approve' => 'Approuver un congé',
            'dashboard.finance.read' => 'Lire la rentabilité / finance',
            'dashboard.sales.read' => 'Lire les indicateurs commerciaux',
            'dashboard.projects.read' => 'Lire les indicateurs chantiers',
            'admin.company.manage' => 'Gérer les utilisateurs et la sécurité tenant',
            'planning.read' => 'Lire le planning',
            'planning.create' => 'Créer une entrée de planning',
            'price.library.read' => 'Lire la bibliothèque de prix',
            'price.library.create' => 'Créer une prestation dans la bibliothèque de prix',
            'project.report.read' => 'Lire les rapports de chantier',
            'project.report.create' => 'Créer des rapports de chantier',
            'project.photo.read' => 'Lire les photos de chantier',
            'project.photo.upload' => 'Téléverser des photos de chantier',
        ];
    }

    /**
     * Structure identique à seed_dev (rôles prédéfinis + permissions).
     *
     * @return array<string, array{permissions: list<string>}>
     */
    public static function rolesStructure(): array
    {
        $all = array_keys(self::permissionCatalog());

        return [
            'Admin' => [
                'permissions' => $all,
            ],
            'Dirigeant' => [
                'permissions' => [
                    'dashboard.finance.read', 'dashboard.sales.read', 'dashboard.projects.read',
                    'client.read', 'quote.read', 'invoice.read', 'project.read',
                ],
            ],
            'Comptable' => [
                'permissions' => [
                    'invoice.read', 'invoice.create', 'invoice.update', 'invoice.mark_paid', 'invoice.export',
                    'dashboard.finance.read',
                ],
            ],
            'Commercial' => [
                'permissions' => [
                    'client.read', 'client.create', 'client.update',
                    'quote.read', 'quote.create', 'quote.send', 'quote.followup',
                    'dashboard.sales.read',
                ],
            ],
            'Conducteur de travaux' => [
                'permissions' => [
                    'project.read', 'project.update',
                    'dashboard.projects.read',
                ],
            ],
            'Chef d’équipe' => [
                'permissions' => [
                    'project.read', 'project.update',
                    'dashboard.projects.read',
                ],
            ],
            'Salarié' => [
                'permissions' => [
                    'project.read',
                ],
            ],
        ];
    }

    /**
     * Crée les Permission, Role et RolePermission pour une société tenant (idempotent).
     */
    public function bootstrapCompany(int $companyId): void
    {
        if ($companyId <= 0) {
            throw new \InvalidArgumentException('companyId invalide.');
        }

        $pdo = Connection::pdo();
        $catalog = self::permissionCatalog();
        $roles = self::rolesStructure();

        $pdo->beginTransaction();
        try {
            $permissionIdByCode = [];
            foreach ($catalog as $code => $desc) {
                $stmt = $pdo->prepare('
                    SELECT id FROM Permission
                    WHERE scope = "tenant" AND companyId = :companyId AND code = :code
                    LIMIT 1
                ');
                $stmt->execute(['companyId' => $companyId, 'code' => $code]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    $permissionIdByCode[$code] = (int) $existing['id'];
                    continue;
                }
                $pdo->prepare('
                    INSERT INTO Permission (scope, companyId, code, description)
                    VALUES ("tenant", :companyId, :code, :description)
                ')->execute([
                    'companyId' => $companyId,
                    'code' => $code,
                    'description' => $desc,
                ]);
                $permissionIdByCode[$code] = (int) $pdo->lastInsertId();
            }

            $roleIdByName = [];
            foreach ($roles as $roleName => $data) {
                $stmt = $pdo->prepare('
                    SELECT id FROM Role
                    WHERE scope = "tenant" AND companyId = :companyId AND name = :name
                    LIMIT 1
                ');
                $stmt->execute(['companyId' => $companyId, 'name' => $roleName]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    $roleIdByName[$roleName] = (int) $existing['id'];
                } else {
                    $code = strtolower(str_replace(' ', '_', $roleName));
                    $pdo->prepare('
                        INSERT INTO Role (scope, companyId, name, code)
                        VALUES ("tenant", :companyId, :name, :code)
                    ')->execute([
                        'companyId' => $companyId,
                        'name' => $roleName,
                        'code' => $code,
                    ]);
                    $roleIdByName[$roleName] = (int) $pdo->lastInsertId();
                }
            }

            foreach ($roles as $roleName => $data) {
                $roleId = $roleIdByName[$roleName] ?? null;
                if ($roleId === null || $roleId <= 0) {
                    continue;
                }
                $pdo->prepare('DELETE FROM RolePermission WHERE companyId = :companyId AND roleId = :roleId')
                    ->execute(['companyId' => $companyId, 'roleId' => $roleId]);

                foreach ($data['permissions'] as $permCode) {
                    if (!isset($permissionIdByCode[$permCode])) {
                        continue;
                    }
                    $pdo->prepare('
                        INSERT INTO RolePermission (companyId, roleId, permissionId, createdAt)
                        VALUES (:companyId, :roleId, :permissionId, NOW())
                    ')->execute([
                        'companyId' => $companyId,
                        'roleId' => $roleId,
                        'permissionId' => $permissionIdByCode[$permCode],
                    ]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Associe un utilisateur au rôle tenant (remplace les UserRole existants pour ce user sur cette société).
     */
    public function assignUserToRole(int $companyId, int $userId, string $roleName = 'Admin'): void
    {
        if ($companyId <= 0 || $userId <= 0) {
            throw new \InvalidArgumentException('Paramètres invalides.');
        }

        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id FROM Role
            WHERE scope = "tenant" AND companyId = :companyId AND name = :name
            LIMIT 1
        ');
        $stmt->execute(['companyId' => $companyId, 'name' => $roleName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \RuntimeException('Rôle tenant introuvable : ' . $roleName . ' (lancez bootstrapCompany).');
        }
        $roleId = (int) $row['id'];

        $pdo->prepare('DELETE FROM UserRole WHERE companyId = :companyId AND userId = :userId')
            ->execute(['companyId' => $companyId, 'userId' => $userId]);

        $pdo->prepare('
            INSERT INTO UserRole (companyId, userId, roleId, createdAt)
            VALUES (:companyId, :userId, :roleId, NOW())
        ')->execute([
            'companyId' => $companyId,
            'userId' => $userId,
            'roleId' => $roleId,
        ]);
    }

    /**
     * Associe l’utilisateur à tous les rôles tenant de la société (utilisateur principal : cumul des périmètres).
     */
    public function assignUserAllTenantRoles(int $companyId, int $userId): void
    {
        if ($companyId <= 0 || $userId <= 0) {
            throw new \InvalidArgumentException('Paramètres invalides.');
        }

        $pdo = Connection::pdo();
        $pdo->prepare('DELETE FROM UserRole WHERE companyId = :companyId AND userId = :userId')
            ->execute(['companyId' => $companyId, 'userId' => $userId]);

        $stmt = $pdo->prepare('
            SELECT id FROM Role
            WHERE scope = "tenant" AND companyId = :companyId
            ORDER BY id ASC
        ');
        $stmt->execute(['companyId' => $companyId]);
        $roleIds = array_map(static fn ($r) => (int) ($r['id'] ?? 0), $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        $roleIds = array_values(array_filter($roleIds, static fn (int $id) => $id > 0));

        if ($roleIds === []) {
            throw new \RuntimeException('Aucun rôle tenant pour cette société (lancez bootstrapCompany).');
        }

        $ins = $pdo->prepare('
            INSERT INTO UserRole (companyId, userId, roleId, createdAt)
            VALUES (:companyId, :userId, :roleId, NOW())
        ');
        foreach ($roleIds as $rid) {
            $ins->execute([
                'companyId' => $companyId,
                'userId' => $userId,
                'roleId' => $rid,
            ]);
        }
    }

    public function getTenantAdminRoleId(int $companyId): ?int
    {
        if ($companyId <= 0) {
            return null;
        }
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            SELECT id FROM Role
            WHERE scope = "tenant" AND companyId = :companyId AND name = :name
            LIMIT 1
        ');
        $stmt->execute(['companyId' => $companyId, 'name' => 'Admin']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int) $row['id'] : null;
    }
}
