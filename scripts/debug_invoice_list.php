<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Core\Autoloader())->register();

use Core\Database\Connection;

$pdo = Connection::pdo();
$stmt = $pdo->prepare('
    SELECT
        id,
        invoiceNumber,
        title,
        dueDate,
        status,
        amountTotal,
        amountPaid,
        (amountTotal - amountPaid) AS amountRemaining
    FROM Invoice
    WHERE companyId = ?
    ORDER BY id DESC
    LIMIT 5
');

$stmt->execute([1]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($rows as $r) {
    echo sprintf(
        "Invoice #%d %s total=%s paid=%s remaining=%s status=%s due=%s\n",
        (int) $r['id'],
        (string) ($r['invoiceNumber'] ?? ''),
        (string) ($r['amountTotal'] ?? 0),
        (string) ($r['amountPaid'] ?? 0),
        (string) ($r['amountRemaining'] ?? 0),
        (string) ($r['status'] ?? ''),
        (string) ($r['dueDate'] ?? '')
    );
}

