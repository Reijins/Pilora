<?php
declare(strict_types=1);

/**
 * Nettoyage des affaires (Project), factures (Invoice) et devis (Quote) pour une société.
 *
 * Usage:
 *   php scripts/cleanup_affaires_factures_devis.php --company-id=1 --dry-run
 *   php scripts/cleanup_affaires_factures_devis.php --company-id=1 --yes
 *
 * Ne supprime pas les clients ni les utilisateurs.
 */

use Core\Autoloader;
use Core\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

function out(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}

function tableExists(\PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('
        SELECT COUNT(*) AS c
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :t
    ');
    $stmt->execute(['t' => $table]);

    return ((int) ($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0)) > 0;
}

function countWhere(\PDO $pdo, string $table, string $where, array $params): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM `{$table}` WHERE {$where}");
    $stmt->execute($params);
    return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
}

$args = array_slice($argv, 1);
$companyId = 0;
$dryRun = false;
$confirm = false;

foreach ($args as $arg) {
    if (str_starts_with($arg, '--company-id=')) {
        $companyId = (int) substr($arg, strlen('--company-id='));
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    } elseif ($arg === '--yes') {
        $confirm = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        out('Usage: php scripts/cleanup_affaires_factures_devis.php --company-id=N [--dry-run] [--yes]');
        out('  --dry-run   Affiche les volumes sans supprimer.');
        out('  --yes       Confirme la suppression (obligatoire hors dry-run).');
        exit(0);
    }
}

if ($companyId <= 0) {
    out('Erreur: précisez --company-id=N (id société / tenant).');
    exit(1);
}

if (!$dryRun && !$confirm) {
    out('Refus: ajoutez --dry-run pour simuler, ou --yes pour supprimer.');
    exit(1);
}

$pdo = Connection::pdo();

$stmt = $pdo->prepare('SELECT id, name FROM Company WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $companyId]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
if (!is_array($company)) {
    out("Erreur: société #{$companyId} introuvable.");
    exit(1);
}

out('Société : ' . (string) ($company['name'] ?? '') . " (id={$companyId})");
out($dryRun ? 'Mode: DRY-RUN (aucune suppression)' : 'Mode: SUPPRESSION');

$c = ['cid' => $companyId];

// Comptages (approximatifs pour dry-run)
$nPay = countWhere(
    $pdo,
    'Payment',
    'invoiceId IN (SELECT id FROM Invoice WHERE companyId = :cid)',
    $c
);
$nInv = countWhere($pdo, 'Invoice', 'companyId = :cid', $c);
$nQuo = countWhere($pdo, 'Quote', 'companyId = :cid', $c);
$nPrj = countWhere($pdo, 'Project', 'companyId = :cid', $c);

$nOtp = tableExists($pdo, 'QuoteSignatureOtp')
    ? countWhere($pdo, 'QuoteSignatureOtp', 'companyId = :cid', $c)
    : 0;
$nShare = tableExists($pdo, 'QuoteShareToken')
    ? countWhere($pdo, 'QuoteShareToken', 'companyId = :cid', $c)
    : 0;

out("À traiter — Paiements liés: {$nPay}, Factures: {$nInv}, Devis: {$nQuo}, Affaires: {$nPrj}");
if ($nOtp > 0 || $nShare > 0) {
    out("          — OTP signature: {$nOtp}, Jetons partage devis: {$nShare}");
}

if ($dryRun) {
    out('Fin dry-run.');
    exit(0);
}

$pdo->beginTransaction();

try {
    // 1) Paiements (factures de la société)
    $pdo->prepare('
        DELETE p FROM Payment p
        INNER JOIN Invoice i ON i.id = p.invoiceId
        WHERE i.companyId = :cid
    ')->execute($c);

    // 2) Factures
    $pdo->prepare('DELETE FROM Invoice WHERE companyId = :cid')->execute($c);

    // 3) OTP signature (pas toujours FK vers Quote)
    if (tableExists($pdo, 'QuoteSignatureOtp')) {
        $pdo->prepare('DELETE FROM QuoteSignatureOtp WHERE companyId = :cid')->execute($c);
    }

    // 4) Jetons partage — CASCADE si table liée ; on supprime aussi par société si besoin
    if (tableExists($pdo, 'QuoteShareToken')) {
        $pdo->prepare('DELETE FROM QuoteShareToken WHERE companyId = :cid')->execute($c);
    }

    // 5) Devis (QuoteItem en CASCADE)
    $pdo->prepare('DELETE FROM Quote WHERE companyId = :cid')->execute($c);

    // 6) Planning / tâches liées aux affaires (évite lignes orphelines si SET NULL)
    if (tableExists($pdo, 'PlanningEntry')) {
        $stmtPe = $pdo->prepare('
            DELETE FROM PlanningEntry
            WHERE companyId = :cid1
              AND projectId IN (SELECT id FROM Project WHERE companyId = :cid2)
        ');
        $stmtPe->execute(['cid1' => $companyId, 'cid2' => $companyId]);
    }
    if (tableExists($pdo, 'Task')) {
        $stmtTk = $pdo->prepare('
            DELETE FROM Task
            WHERE companyId = :cid1
              AND projectId IN (SELECT id FROM Project WHERE companyId = :cid2)
        ');
        $stmtTk->execute(['cid1' => $companyId, 'cid2' => $companyId]);
    }

    // 7) Affaires (ProjectReport, ProjectPhoto, ProjectAssignment en CASCADE selon schéma)
    $pdo->prepare('DELETE FROM Project WHERE companyId = :cid')->execute($c);

    $pdo->commit();
    out('Nettoyage terminé avec succès.');
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Erreur: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
