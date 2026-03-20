<?php
declare(strict_types=1);

namespace Modules\Projects\Controllers;

use App\Controllers\BaseController;
use Core\Context\UserContext;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\Csrf;
use Modules\Projects\Repositories\ProjectReportRepository;
use Modules\Projects\Repositories\ProjectRepository;

final class ProjectReportsController extends BaseController
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

        $hasRead = in_array('project.report.read', $userContext->permissions, true);
        $hasCreate = in_array('project.report.create', $userContext->permissions, true);
        if (!$hasRead) {
            return $this->renderPage('project_reports/index.php', [
                'pageTitle' => 'Rapports chantier',
                'permissionDenied' => true,
                'projectId' => $projectId,
            ]);
        }

        $repoReports = new ProjectReportRepository();
        $reports = [];
        try {
            $reports = $repoReports->listByCompanyIdAndProjectId($userContext->companyId, $projectId);
        } catch (\Throwable) {
            $reports = [];
        }

        // Récupérer un nom de projet (optionnel) pour l’affichage.
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

        return $this->renderPage('project_reports/index.php', [
            'pageTitle' => 'Rapports chantier',
            'permissionDenied' => false,
            'csrfToken' => Csrf::token(),
            'projectId' => $projectId,
            'projectName' => $projectName,
            'reports' => $reports,
            'canCreate' => $hasCreate,
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
        ]);
    }

    public function create(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        if (!in_array('project.report.create', $userContext->permissions, true)) {
            return Response::redirect('clients?err=Permissions%20insuffisantes');
        }

        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('project-reports?projectId=' . $request->getBodyParam('project_id', 0) . '&err=CSRF%20invalide');
        }

        $projectIdRaw = $request->getBodyParam('project_id', null);
        $projectId = is_numeric($projectIdRaw) ? (int) $projectIdRaw : 0;
        if ($projectId <= 0) {
            return Response::redirect('clients?err=Affaire%20invalide');
        }

        $title = trim((string) $request->getBodyParam('title', ''));
        $content = trim((string) $request->getBodyParam('content', ''));
        if ($title === '') {
            return Response::redirect('project-reports?projectId=' . $projectId . '&err=Titre%20requis');
        }

        $repo = new ProjectReportRepository();
        try {
            $repo->create(
                companyId: $userContext->companyId,
                projectId: $projectId,
                authorUserId: $userContext->userId,
                title: $title,
                content: $content !== '' ? $content : null,
            );
        } catch (\Throwable) {
            return Response::redirect('project-reports?projectId=' . $projectId . '&err=Impossible%20de%20cr%C3%A9er%20le%20rapport');
        }

        Csrf::rotate();
        return Response::redirect('project-reports?projectId=' . $projectId . '&msg=Rapport%20cr%C3%A9%C3%A9');
    }
}

