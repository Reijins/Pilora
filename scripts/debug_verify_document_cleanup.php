<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Core\Autoloader())->register();

use Core\Database\Connection;

$pdo = Connection::pdo();

$tableExists = $pdo->query("SHOW TABLES LIKE 'Document'")->fetchColumn();
echo ($tableExists ? 'Document_exists' : 'Document_absent') . PHP_EOL;

$stmt = $pdo->query('
    SELECT COUNT(*) AS c
    FROM Permission
    WHERE code IN ("document.read", "document.upload")
');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo 'document_permissions_count=' . (int) ($row['c'] ?? 0) . PHP_EOL;

