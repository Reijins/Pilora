<?php
declare(strict_types=1);

namespace Modules\Dashboard\Controllers;

use App\Controllers\BaseController;
use Core\Context\UserContext;
use Core\Http\Request;
use Core\Http\Response;
use Modules\Dashboard\Repositories\DashboardKpiRepository;

final class DashboardController extends BaseController
{
    public function index(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        $kpis = [
            'quotesToFollowUp' => 0,
            'overdueInvoices' => 0,
            'lateProjects' => 0,
            'missingDocs' => 0,
        ];
        try {
            $repo = new DashboardKpiRepository();
            $cid = (int) $userContext->companyId;
            $kpis = [
                'quotesToFollowUp' => $repo->countQuotesToFollowUp($cid),
                'overdueInvoices' => $repo->countOverdueUnpaidInvoices($cid),
                'lateProjects' => $repo->countLateActiveProjects($cid),
                'missingDocs' => $repo->countProjectsMissingRecentReports($cid, 14),
            ];
        } catch (\Throwable) {
            // garder des zéros si requête / schéma indisponible
        }

        return $this->renderPage('dashboard/index.php', [
            'pageTitle' => 'Tableau de bord',
            'dashboardKpis' => $kpis,
        ]);
    }
}

