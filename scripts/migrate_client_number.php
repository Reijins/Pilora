<?php
declare(strict_types=1);

/**
 * Ajoute clientNumber (C00001) sur Client et projectNumber (A202600001) sur Project.
 * Génère les numéros manquants pour les enregistrements existants.
 *
 * Usage : php scripts/migrate_client_number.php
 */

use Core\Autoloader;
use Core\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

$pdo = Connection::pdo();

// --- Client.clientNumber ---
$cols = $pdo->query("SHOW COLUMNS FROM Client LIKE 'clientNumber'")->fetchAll();
if ($cols === []) {
    $pdo->exec("ALTER TABLE Client ADD COLUMN clientNumber VARCHAR(10) NULL AFTER companyId");
    $pdo->exec("ALTER TABLE Client ADD UNIQUE KEY idx_client_number (companyId, clientNumber)");
    echo "Migration: colonne Client.clientNumber ajoutée\n";

    // Générer les numéros pour les clients existants par company
    $companies = $pdo->query("SELECT DISTINCT companyId FROM Client")->fetchAll(\PDO::FETCH_COLUMN);
    foreach ($companies as $companyId) {
        $stmt = $pdo->prepare("SELECT id FROM Client WHERE companyId = :cid ORDER BY id ASC");
        $stmt->execute(['cid' => $companyId]);
        $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $seq = 1;
        $upd = $pdo->prepare("UPDATE Client SET clientNumber = :num WHERE id = :id");
        foreach ($ids as $id) {
            $num = 'C' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
            $upd->execute(['num' => $num, 'id' => $id]);
            $seq++;
        }
    }
    echo "Migration: numéros clients existants générés\n";
} else {
    echo "Migration: Client.clientNumber déjà présente\n";
}

// --- Project.projectNumber ---
$cols2 = $pdo->query("SHOW COLUMNS FROM Project LIKE 'projectNumber'")->fetchAll();
if ($cols2 === []) {
    $pdo->exec("ALTER TABLE Project ADD COLUMN projectNumber VARCHAR(15) NULL AFTER companyId");
    $pdo->exec("ALTER TABLE Project ADD UNIQUE KEY idx_project_number (companyId, projectNumber)");
    echo "Migration: colonne Project.projectNumber ajoutée\n";

    // Générer les numéros pour les projets existants par company
    $companies = $pdo->query("SELECT DISTINCT companyId FROM Project")->fetchAll(\PDO::FETCH_COLUMN);
    foreach ($companies as $companyId) {
        $stmt = $pdo->prepare("SELECT id, createdAt FROM Project WHERE companyId = :cid ORDER BY id ASC");
        $stmt->execute(['cid' => $companyId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $seqByYear = [];
        $upd = $pdo->prepare("UPDATE Project SET projectNumber = :num WHERE id = :id");
        foreach ($rows as $row) {
            $year = date('Y', strtotime($row['createdAt']));
            if (!isset($seqByYear[$year])) {
                $seqByYear[$year] = 1;
            }
            $num = 'A' . $year . str_pad((string) $seqByYear[$year], 5, '0', STR_PAD_LEFT);
            $upd->execute(['num' => $num, 'id' => $row['id']]);
            $seqByYear[$year]++;
        }
    }
    echo "Migration: numéros projets existants générés\n";
} else {
    echo "Migration: Project.projectNumber déjà présente\n";
}

echo "Terminé.\n";
