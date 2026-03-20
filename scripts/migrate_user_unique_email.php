<?php
declare(strict_types=1);

use Core\Autoloader;
use Core\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

$pdo = Connection::pdo();

$table = 'User';
$desiredIndexName = 'uq_user_company_email';
$currentIndexName = 'uq_user_email';

function hasIndex(PDO $pdo, string $table, string $indexName): bool
{
    $stmt = $pdo->prepare('
        SELECT COUNT(*) AS c
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = :table
          AND index_name = :indexName
    ');
    $stmt->execute(['table' => $table, 'indexName' => $indexName]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return ((int) ($row['c'] ?? 0)) > 0;
}

try {
    if (hasIndex($pdo, $table, $currentIndexName)) {
        $pdo->exec('ALTER TABLE `'.$table.'` DROP INDEX `'.$currentIndexName.'`');
        fwrite(STDOUT, "Migration: index courant supprimé: {$currentIndexName}\n");
    }

    if (!hasIndex($pdo, $table, $desiredIndexName)) {
        $pdo->exec('
            ALTER TABLE `'.$table.'`
            ADD UNIQUE KEY `'.$desiredIndexName.'` (companyId, email)
        ');
        fwrite(STDOUT, "Migration: index ajouté: {$desiredIndexName}\n");
    } else {
        fwrite(STDOUT, "Migration: index déjà présent: {$desiredIndexName}\n");
    }

    fwrite(STDOUT, "Migration: OK\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Migration: ERREUR: " . $e->getMessage() . "\n");
    throw $e;
}

