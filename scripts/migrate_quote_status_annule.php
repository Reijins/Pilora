<?php
declare(strict_types=1);

use Core\Autoloader;
use Core\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

$pdo = Connection::pdo();

$pdo->exec("
ALTER TABLE Quote
  MODIFY COLUMN status ENUM(
    'brouillon',
    'envoye',
    'a_relancer',
    'accepte',
    'refuse',
    'annule'
  ) NOT NULL DEFAULT 'brouillon'
");

echo "Migration: Quote.status + annule OK\n";
