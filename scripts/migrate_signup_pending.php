<?php
declare(strict_types=1);

/**
 * Table des inscriptions en cours (paiement Stripe ou essai gratuit).
 *
 * Usage : php scripts/migrate_signup_pending.php
 */

use Core\Autoloader;
use Core\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

$pdo = Connection::pdo();

$pdo->exec('
    CREATE TABLE IF NOT EXISTS SignupPending (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      token VARCHAR(64) NOT NULL,
      status ENUM("pending","paid","provisioned","failed","cancelled") NOT NULL DEFAULT "pending",
      stripeCheckoutSessionId VARCHAR(255) NULL,
      payload JSON NOT NULL,
      companyId BIGINT UNSIGNED NULL,
      userId BIGINT UNSIGNED NULL,
      createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_signup_token (token),
      KEY idx_signup_stripe_session (stripeCheckoutSessionId),
      KEY idx_signup_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
');

echo "Migration: table SignupPending OK\n";
