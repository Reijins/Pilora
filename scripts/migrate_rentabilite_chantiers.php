<?php
declare(strict_types=1);

/**
 * Rentabilité chantiers / affaires :
 * - Company.workHoursPerDay (conversion jours ↔ heures)
 * - User.coutHoraire
 * - Project : montants rentabilité + statut saisie (les dates fin prévue / réelle = plannedEndDate / actualEndDate)
 * - ProjectTimeEntry : temps passé (minutes) — ne pas confondre avec ProjectAssignment (équipe)
 */
use Core\Autoloader;
use Core\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Autoloader())->register();

$pdo = Connection::pdo();

$statements = [
    <<<'SQL'
ALTER TABLE Company
  ADD COLUMN workHoursPerDay DECIMAL(5,2) NOT NULL DEFAULT 8.00
  AFTER name
SQL
    ,
    <<<'SQL'
ALTER TABLE `User`
  ADD COLUMN coutHoraire DECIMAL(10,2) NULL
  AFTER phone
SQL
    ,
    <<<'SQL'
ALTER TABLE Project
  ADD COLUMN montantFactureHt DECIMAL(12,2) NULL AFTER actualEndDate,
  ADD COLUMN coutMateriauxTotal DECIMAL(12,2) NULL DEFAULT 0 AFTER montantFactureHt,
  ADD COLUMN rentabiliteStatut ENUM('a_renseigner','renseignee') NOT NULL DEFAULT 'a_renseigner' AFTER coutMateriauxTotal,
  ADD COLUMN rentabiliteRenseigneeAt DATETIME NULL AFTER rentabiliteStatut,
  ADD COLUMN beneficeTotal DECIMAL(12,2) NULL AFTER rentabiliteRenseigneeAt,
  ADD COLUMN margePercent DECIMAL(8,2) NULL AFTER beneficeTotal
SQL
    ,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS ProjectTimeEntry (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  projectId BIGINT UNSIGNED NOT NULL,
  userId BIGINT UNSIGNED NOT NULL,
  assignmentDate DATE NOT NULL,
  durationMinutes INT UNSIGNED NOT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_timeEntry_company_project (companyId, projectId),
  KEY idx_timeEntry_user (companyId, userId),
  CONSTRAINT fk_timeEntry_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_timeEntry_project
    FOREIGN KEY (projectId) REFERENCES Project (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_timeEntry_user
    FOREIGN KEY (userId) REFERENCES `User` (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    ,
    <<<'SQL'
UPDATE Project
SET actualEndDate = plannedEndDate
WHERE status = 'completed'
  AND actualEndDate IS NULL
  AND plannedEndDate IS NOT NULL
SQL
    ,
];

foreach ($statements as $sql) {
    try {
        $pdo->exec($sql);
    } catch (\PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate column')
            || str_contains($e->getMessage(), 'duplicate column')
            || ($e->getCode() === '42S21')) {
            echo "(skip colonne ou table déjà présente)\n";
            continue;
        }
        if (str_contains($e->getMessage(), 'already exists')
            || str_contains($e->getMessage(), 'Duplicate key')) {
            echo "(skip table déjà créée)\n";
            continue;
        }
        throw $e;
    }
}

echo "Migration rentabilité chantiers OK.\n";
