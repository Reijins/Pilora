<?php
declare(strict_types=1);

use Core\Autoloader;
use Core\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

$pdo = Connection::pdo();

$pdo->exec("
ALTER TABLE Invoice
  MODIFY COLUMN status ENUM(
    'brouillon',
    'envoyee',
    'partiellement_payee',
    'payee',
    'echue',
    'annulee'
  ) NOT NULL DEFAULT 'brouillon'
");

echo "Migration: Invoice.status + annulee OK\n";
