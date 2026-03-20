<?php
declare(strict_types=1);

namespace Modules\Projects\Controllers;

use App\Controllers\BaseController;
use Core\Context\UserContext;
use Core\Database\Connection;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\Csrf;
use Modules\Projects\Repositories\ProjectPhotoRepository;
use Modules\Projects\Repositories\ProjectRepository;

final class ProjectPhotosController extends BaseController
{
    public function index(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        $projectIdRaw = $request->getQueryParam('projectId', 0);
        $projectId = is_numeric($projectIdRaw) ? (int) $projectIdRaw : 0;
        if ($projectId <= 0) {
            return Response::redirect('clients?err=Affaire%20invalide');
        }

        $hasRead = in_array('project.photo.read', $userContext->permissions, true);
        $hasUpload = in_array('project.photo.upload', $userContext->permissions, true);
        if (!$hasRead) {
            return $this->renderPage('project_photos/index.php', [
                'pageTitle' => 'Photos chantier',
                'permissionDenied' => true,
                'projectId' => $projectId,
            ]);
        }

        $repoPhotos = new ProjectPhotoRepository();
        $photos = [];
        try {
            $photos = $repoPhotos->listByCompanyIdAndProjectId($userContext->companyId, $projectId);
        } catch (\Throwable) {
            $photos = [];
        }

        $projectName = '';
        try {
            $repoProjects = new ProjectRepository();
            $projects = $repoProjects->listByCompanyId($userContext->companyId, 200);
            foreach ($projects as $p) {
                if ((int) ($p['id'] ?? 0) === $projectId) {
                    $projectName = (string) ($p['name'] ?? '');
                    break;
                }
            }
        } catch (\Throwable) {
            $projectName = '';
        }

        return $this->renderPage('project_photos/index.php', [
            'pageTitle' => 'Photos chantier',
            'permissionDenied' => false,
            'csrfToken' => Csrf::token(),
            'projectId' => $projectId,
            'projectName' => $projectName,
            'photos' => $photos,
            'canUpload' => $hasUpload,
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
        ]);
    }

    public function upload(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        if (!in_array('project.photo.upload', $userContext->permissions, true)) {
            return Response::redirect('clients?err=Permissions%20insuffisantes');
        }

        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('project-photos?projectId=' . (string) $request->getBodyParam('project_id', 0) . '&err=CSRF%20invalide');
        }

        $projectIdRaw = $request->getBodyParam('project_id', null);
        $projectId = is_numeric($projectIdRaw) ? (int) $projectIdRaw : 0;
        if ($projectId <= 0) {
            return Response::redirect('clients?err=Affaire%20invalide');
        }

        if (!isset($_FILES['photo']) || !is_array($_FILES['photo'])) {
            return Response::redirect('project-photos?projectId=' . $projectId . '&err=Fichier%20manquant');
        }

        $file = $_FILES['photo'];
        if (!isset($file['error']) || !is_int($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return Response::redirect('project-photos?projectId=' . $projectId . '&err=Erreur%20de%20téléversement');
        }

        $maxBytes = 5 * 1024 * 1024; // 5MB
        $size = isset($file['size']) && is_int($file['size']) ? $file['size'] : 0;
        if ($size <= 0 || $size > $maxBytes) {
            return Response::redirect('project-photos?projectId=' . $projectId . '&err=Fichier%20trop%20volumineux');
        }

        $tmpPath = isset($file['tmp_name']) && is_string($file['tmp_name']) ? $file['tmp_name'] : '';
        if ($tmpPath === '' || !is_file($tmpPath)) {
            return Response::redirect('project-photos?projectId=' . $projectId . '&err=Fichier%20temp%20invalide');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath) ?: '';

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if (!isset($allowed[$mime])) {
            return Response::redirect('project-photos?projectId=' . $projectId . '&err=Type%20de%20fichier%20non%20autorisé');
        }

        $fileName = 'photo_' . bin2hex(random_bytes(16)) . '.webp';

        $appRoot = dirname(__DIR__, 3); // pilora/
        $storageDir = $appRoot . '/public/storage/uploads/' . $userContext->companyId . '/projects/' . $projectId . '/';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }

        $destPath = $storageDir . $fileName;

        if (!$this->convertToWebp($tmpPath, $destPath, $mime)) {
            return Response::redirect('project-photos?projectId=' . $projectId . '&err=Impossible%20de%20convertir%20la%20photo');
        }

        $caption = trim((string) $request->getBodyParam('caption', ''));
        $caption = $caption !== '' ? $caption : null;

        $relativeFilePath = '/public/storage/uploads/' . $userContext->companyId . '/projects/' . $projectId . '/' . $fileName;

        // Persist photo record
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('
            INSERT INTO ProjectPhoto (companyId, projectId, uploaderUserId, filePath, caption, takenAt, createdAt, updatedAt)
            VALUES (:companyId, :projectId, :uploaderUserId, :filePath, :caption, NULL, NOW(), NOW())
        ');
        $stmt->execute([
            'companyId' => $userContext->companyId,
            'projectId' => $projectId,
            'uploaderUserId' => $userContext->userId,
            'filePath' => $relativeFilePath,
            'caption' => $caption,
        ]);

        Csrf::rotate();
        return Response::redirect('project-photos?projectId=' . $projectId . '&msg=Photo%20enregistr%C3%A9e');
    }

    private function convertToWebp(string $sourcePath, string $destinationPath, string $mime): bool
    {
        if (!function_exists('imagewebp')) {
            return false;
        }
        $image = $this->createImageResource($sourcePath, $mime);
        if (!is_resource($image) && !($image instanceof \GdImage)) {
            return false;
        }

        // Preserve alpha for PNG/GIF to avoid black background.
        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($image);
        }
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $ok = imagewebp($image, $destinationPath, 82);
        imagedestroy($image);

        return $ok && is_file($destinationPath);
    }

    /**
     * @return resource|\GdImage|null
     */
    private function createImageResource(string $sourcePath, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/webp' => @imagecreatefromwebp($sourcePath),
            'image/gif' => @imagecreatefromgif($sourcePath),
            default => null,
        };
    }
}

