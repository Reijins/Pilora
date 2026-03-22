<?php
declare(strict_types=1);

use Core\Autoloader;
use Core\Database\Connection;
use Core\Config;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

function out(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}

$pdo = Connection::pdo();

$seedForce = ((int) (Config::env('SEED_FORCE', '0') ?? '0')) === 1;
$companyName = (string) Config::env('SEED_COMPANY_NAME', 'Pilora Demo');
$adminEmail = (string) Config::env('SEED_ADMIN_EMAIL', 'admin@pilora.demo');
$adminPassword = (string) Config::env('SEED_ADMIN_PASSWORD', 'Admin12345!');
$adminFullName = (string) Config::env('SEED_ADMIN_FULL_NAME', 'Admin Pilora');

$roles = [
    'Admin' => [
        'permissions' => [
            // Clients
            'client.read','client.create','client.update','client.delete',
            // Devis
            'quote.read','quote.create','quote.send','quote.followup',
            // Factures
            'invoice.read','invoice.create','invoice.update','invoice.mark_paid','invoice.export',
            // Projets/Chantiers
            'project.read','project.create','project.update',
            // RH
            'hr.leave.request','hr.leave.approve',
            // Dashboard
            'dashboard.finance.read','dashboard.sales.read','dashboard.projects.read',
            // Admin plateforme (tenant)
            'admin.company.manage'
        ],
    ],
    'Dirigeant' => [
        'permissions' => [
            'dashboard.finance.read','dashboard.sales.read','dashboard.projects.read',
            'client.read','quote.read','invoice.read','project.read',
        ],
    ],
    'Comptable' => [
        'permissions' => [
            'invoice.read','invoice.create','invoice.update','invoice.mark_paid','invoice.export',
            'dashboard.finance.read',
        ],
    ],
    'Commercial' => [
        'permissions' => [
            'client.read','client.create','client.update',
            'quote.read','quote.create','quote.send','quote.followup',
            'dashboard.sales.read',
        ],
    ],
    'Conducteur de travaux' => [
        'permissions' => [
            'project.read','project.update',
            'dashboard.projects.read',
        ],
    ],
    'Chef d’équipe' => [
        'permissions' => [
            'project.read','project.update',
            'dashboard.projects.read',
        ],
    ],
    'Salarié' => [
        'permissions' => [
            'project.read',
        ],
    ],
];

$permissionDescriptions = [
    // Clients / CRM
    'client.read' => 'Lire les clients',
    'client.create' => 'Créer des clients',
    'client.update' => 'Mettre à jour les clients',
    'client.delete' => 'Supprimer des clients',
    // Devis
    'quote.read' => 'Lire les devis',
    'quote.create' => 'Créer des devis',
    'quote.send' => 'Envoyer des devis',
    'quote.followup' => 'Gérer les relances devis',
    // Factures
    'invoice.read' => 'Lire les factures',
    'invoice.create' => 'Créer des factures',
    'invoice.update' => 'Mettre à jour les factures',
    'invoice.mark_paid' => 'Marquer une facture payée',
    'invoice.export' => 'Exporter en CSV pour comptabilité',
    // Projets / Chantiers
    'project.read' => 'Lire les chantiers',
    'project.create' => 'Créer des chantiers',
    'project.update' => 'Mettre à jour les chantiers',
    // RH
    'hr.leave.request' => 'Demander un congé',
    'hr.leave.approve' => 'Approuver un congé',
    // Dashboard
    'dashboard.finance.read' => 'Lire la rentabilité / finance',
    'dashboard.sales.read' => 'Lire les indicateurs commerciaux',
    'dashboard.projects.read' => 'Lire les indicateurs chantiers',
    // Admin tenant
    'admin.company.manage' => 'Gérer les utilisateurs et la sécurité tenant',
];

$pdo->beginTransaction();
try {
    // Company
    $companyId = null;
    $stmt = $pdo->prepare('SELECT id FROM Company WHERE name = :name LIMIT 1');
    $stmt->execute(['name' => $companyName]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $companyId = (int) $row['id'];
        if (!$seedForce) {
            out("Seed: company déjà existante (id=$companyId). SEED_FORCE=0 => skip.");
            $pdo->commit();
            exit(0);
        }
    } else {
        $stmt = $pdo->prepare('INSERT INTO Company (name, status) VALUES (:name, "active")');
        $stmt->execute(['name' => $companyName]);
        $companyId = (int) $pdo->lastInsertId();
        out("Seed: company créée (id=$companyId).");
    }

    // Optionally wipe security tables for the company (only when force)
    if ($seedForce) {
        $pdo->prepare('DELETE FROM RolePermission WHERE companyId = :companyId')->execute(['companyId' => $companyId]);
        $pdo->prepare('DELETE FROM UserRole WHERE companyId = :companyId')->execute(['companyId' => $companyId]);
        $pdo->prepare('DELETE FROM Permission WHERE companyId = :companyId')->execute(['companyId' => $companyId]);
        $pdo->prepare('DELETE FROM Role WHERE companyId = :companyId')->execute(['companyId' => $companyId]);
        $pdo->prepare('DELETE FROM `User` WHERE companyId = :companyId')->execute(['companyId' => $companyId]);
        out("Seed: sécurité et utilisateurs tenant vidés (SEED_FORCE=1).");
    }

    // Permissions
    $permissionIdByCode = [];
    foreach ($permissionDescriptions as $code => $desc) {
        $stmt = $pdo->prepare('SELECT id FROM Permission WHERE scope="tenant" AND companyId=:companyId AND code=:code LIMIT 1');
        $stmt->execute(['companyId' => $companyId, 'code' => $code]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $permissionIdByCode[$code] = (int) $existing['id'];
            continue;
        }

        $stmt = $pdo->prepare('
            INSERT INTO Permission (scope, companyId, code, description)
            VALUES ("tenant", :companyId, :code, :description)
        ');
        $stmt->execute([
            'companyId' => $companyId,
            'code' => $code,
            'description' => $desc,
        ]);
        $permissionIdByCode[$code] = (int) $pdo->lastInsertId();
    }

    // Roles + RolePermission
    $roleIdByName = [];
    foreach ($roles as $roleName => $data) {
        $stmt = $pdo->prepare('
            SELECT id FROM Role
            WHERE scope="tenant" AND companyId=:companyId AND name=:name
            LIMIT 1
        ');
        $stmt->execute(['companyId' => $companyId, 'name' => $roleName]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $roleIdByName[$roleName] = (int) $existing['id'];
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO Role (scope, companyId, name, code)
                VALUES ("tenant", :companyId, :name, :code)
            ');
            $stmt->execute([
                'companyId' => $companyId,
                'name' => $roleName,
                'code' => strtolower(str_replace(' ', '_', $roleName)),
            ]);
            $roleIdByName[$roleName] = (int) $pdo->lastInsertId();
        }
    }

    // Assign permissions to roles
    foreach ($roles as $roleName => $data) {
        $roleId = $roleIdByName[$roleName];
        // remove old mappings (safe even when not force)
        $pdo->prepare('DELETE FROM RolePermission WHERE companyId=:companyId AND roleId=:roleId')
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

    // Admin user
    $stmt = $pdo->prepare('SELECT id FROM `User` WHERE companyId=:companyId AND email=:email LIMIT 1');
    $stmt->execute(['companyId' => $companyId, 'email' => $adminEmail]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingUser) {
        $userId = (int) $existingUser['id'];
    } else {
        $hash = password_hash($adminPassword, PASSWORD_BCRYPT);
        $pdo->prepare('
            INSERT INTO `User` (companyId, email, passwordHash, fullName, status)
            VALUES (:companyId, :email, :hash, :fullName, "active")
        ')->execute([
            'companyId' => $companyId,
            'email' => $adminEmail,
            'hash' => $hash,
            'fullName' => $adminFullName,
        ]);
        $userId = (int) $pdo->lastInsertId();
    }

    // Assign Admin role to admin user
    $adminRoleId = $roleIdByName['Admin'] ?? null;
    if ($adminRoleId !== null) {
        $pdo->prepare('DELETE FROM UserRole WHERE companyId=:companyId AND userId=:userId')
            ->execute(['companyId' => $companyId, 'userId' => $userId]);

        $pdo->prepare('
            INSERT INTO UserRole (companyId, userId, roleId, createdAt)
            VALUES (:companyId, :userId, :roleId, NOW())
        ')->execute([
            'companyId' => $companyId,
            'userId' => $userId,
            'roleId' => $adminRoleId,
        ]);
    }

    $pdo->commit();
    out("Seed: terminé.");
    out("Connexion admin: {$adminEmail} / {$adminPassword}");
    out("CompanyId: {$companyId}");
} catch (Throwable $e) {
    $pdo->rollBack();
    out('Seed: erreur: ' . $e->getMessage());
    throw $e;
}

