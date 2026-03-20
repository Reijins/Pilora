<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Core\Autoloader())->register();

use Core\Database\Connection;
use Modules\Projects\Repositories\ProjectReportRepository;
use Modules\Projects\Repositories\ProjectPhotoRepository;

$pdo = Connection::pdo();
$stmt = $pdo->prepare('
    SELECT id FROM Project WHERE companyId=? ORDER BY id DESC LIMIT 1
');
$stmt->execute([1]);
$projectId = (int) ($stmt->fetchColumn() ?: 0);

echo "projectId={$projectId}\n";
if ($projectId <= 0) {
    exit(0);
}

$rep = new ProjectReportRepository();
$reports = $rep->listByCompanyIdAndProjectId(1, $projectId, 10);
echo "reportsCount=" . count($reports) . "\n";
if (!empty($reports)) {
    echo "firstReportTitle=" . (string) ($reports[0]['title'] ?? '') . "\n";
}

$pho = new ProjectPhotoRepository();
$photos = $pho->listByCompanyIdAndProjectId(1, $projectId, 10);
echo "photosCount=" . count($photos) . "\n";

