<?php
declare(strict_types=1);

namespace Modules\Planning\Controllers;

use App\Controllers\BaseController;
use Core\Context\UserContext;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\Csrf;
use Modules\Planning\Repositories\PlanningRepository;
use Modules\Projects\Repositories\ProjectRepository;

final class PlanningController extends BaseController
{
    public function index(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        $canRead = in_array('planning.read', $userContext->permissions, true);
        if (!$canRead) {
            return $this->renderPage('planning/index.php', [
                'pageTitle' => 'Planning',
                'permissionDenied' => true,
            ]);
        }

        $weekStartRaw = $request->getQueryParam('weekStart', '');
        $weekStart = is_string($weekStartRaw) && trim($weekStartRaw) !== '' ? trim($weekStartRaw) : date('Y-m-d');
        $weekStartDate = new \DateTimeImmutable($weekStart . ' 00:00:00');
        $dayOfWeek = (int) $weekStartDate->format('N');
        $monday = $weekStartDate->modify('-' . ($dayOfWeek - 1) . ' days');
        $rangeStart = $monday->setTime(0, 0, 0);
        $rangeEnd = $monday->modify('+6 days')->setTime(23, 59, 59);
        $repoProjects = new ProjectRepository();
        $scheduledProjects = [];
        try {
            $scheduledProjects = $repoProjects->listScheduledForRange(
                companyId: $userContext->companyId,
                rangeStartYmd: $rangeStart->format('Y-m-d'),
                rangeEndYmd: $rangeEnd->format('Y-m-d')
            );
        } catch (\Throwable) {
            $scheduledProjects = [];
        }

        $weekDays = [];
        $projectsByDay = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $monday->modify('+' . $i . ' days');
            $key = $d->format('Y-m-d');
            $weekDays[] = $key;
            $projectsByDay[$key] = [];
        }
        foreach ($scheduledProjects as $p) {
            $start = trim((string) ($p['plannedStartDate'] ?? ''));
            $end = trim((string) ($p['plannedEndDate'] ?? ''));
            if ($start === '' || $end === '') {
                continue;
            }
            foreach ($weekDays as $day) {
                if ($day >= $start && $day <= $end) {
                    $projectsByDay[$day][] = $p;
                }
            }
        }

        return $this->renderPage('planning/index.php', [
            'pageTitle' => 'Planning',
            'permissionDenied' => false,
            'projectsByDay' => $projectsByDay,
            'weekDays' => $weekDays,
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
            'weekStart' => $monday->format('Y-m-d'),
            'prevWeekStart' => $monday->modify('-7 days')->format('Y-m-d'),
            'nextWeekStart' => $monday->modify('+7 days')->format('Y-m-d'),
            'rangeStart' => $rangeStart->format('Y-m-d'),
            'rangeEnd' => $rangeEnd->format('Y-m-d'),
        ]);
    }

    public function create(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('planning.create', $userContext->permissions, true)) {
            return Response::redirect('planning');
        }

        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('planning?err=CSRF%20invalide');
        }

        $projectIdRaw = $request->getBodyParam('project_id', null);
        $projectId = is_numeric($projectIdRaw) ? (int) $projectIdRaw : null;

        $userIdRaw = $request->getBodyParam('user_id', null);
        $userId = is_numeric($userIdRaw) ? (int) $userIdRaw : null;

        $entryType = trim((string) $request->getBodyParam('entry_type', 'task'));
        $title = trim((string) $request->getBodyParam('title', ''));
        $notes = trim((string) $request->getBodyParam('notes', ''));

        $startAtRaw = trim((string) $request->getBodyParam('start_at', ''));
        $endAtRaw = trim((string) $request->getBodyParam('end_at', ''));
        if ($title === '') {
            return Response::redirect('planning?err=Titre%20requis');
        }
        if ($startAtRaw === '' || $endAtRaw === '') {
            return Response::redirect('planning?err=Dates%20requises');
        }

        $startAt = new \DateTimeImmutable($startAtRaw);
        $endAt = new \DateTimeImmutable($endAtRaw);

        $repoPlanning = new PlanningRepository();
        try {
            // Déduplication: évite les doubles saisies identiques
            // lors de double-clic / double POST / refresh trop rapide.
            $duplicateId = $repoPlanning->findDuplicateEntryId(
                companyId: $userContext->companyId,
                projectId: $projectId,
                taskId: null,
                userId: $userId,
                entryType: $entryType,
                title: $title,
                startAt: $startAt,
                endAt: $endAt,
            );

            if ($duplicateId !== null) {
                Csrf::rotate();
                return Response::redirect('planning?msg=Entr%C3%A9e%20d%C3%A9j%C3%A0%20existante');
            }

            $repoPlanning->createPlanningEntry(
                companyId: $userContext->companyId,
                projectId: $projectId,
                taskId: null,
                userId: $userId,
                entryType: $entryType,
                title: $title,
                notes: $notes !== '' ? $notes : null,
                startAt: $startAt,
                endAt: $endAt,
                createdByUserId: $userContext->userId,
            );
        } catch (\Throwable) {
            return Response::redirect('planning?err=Impossible%20d%27enregistrer');
        }

        Csrf::rotate();
        return Response::redirect('planning?msg=Entr%C3%A9e%20planning%20cr%C3%A9%C3%A9e');
    }
}

