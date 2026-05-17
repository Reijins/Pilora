<?php
declare(strict_types=1);

/**
 * Ajoute Company.subscriptionStartedAt (date de début du pack / abonnement).
 *
 * Usage : php scripts/migrate_subscription_started_at.php
 */

use Core\Autoloader;
use Core\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

$pdo = Connection::pdo();

$check = $pdo->query("SHOW COLUMNS FROM Company LIKE 'subscriptionStartedAt'");
if ($check !== false && $check->rowCount() === 0) {
    $pdo->exec('
        ALTER TABLE Company
            ADD COLUMN subscriptionStartedAt DATE NULL AFTER maxSeats
    ');
    echo "Migration: colonne subscriptionStartedAt ajoutée\n";
} else {
    echo "Migration: colonne subscriptionStartedAt déjà présente\n";
}

$upd = $pdo->exec('
    UPDATE Company
    SET subscriptionStartedAt = DATE(createdAt)
    WHERE subscriptionStartedAt IS NULL
      AND billingPlan IS NOT NULL
      AND TRIM(billingPlan) <> ""
');
if ($upd !== false && $upd > 0) {
    echo "Migration: {$upd} société(s) — date de début renseignée depuis createdAt\n";
}

echo "Migration terminée.\n";
