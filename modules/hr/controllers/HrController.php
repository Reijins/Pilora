<?php
declare(strict_types=1);

namespace Modules\Hr\Controllers;

use App\Controllers\BaseController;
use Core\Context\UserContext;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\Csrf;
use Modules\Hr\Repositories\LeaveRequestRepository;

final class HrController extends BaseController
{
    public function index(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }

        $canRequest = in_array('hr.leave.request', $userContext->permissions, true);
        $canApprove = in_array('hr.leave.approve', $userContext->permissions, true);
        if (!$canRequest && !$canApprove) {
            return $this->renderPage('hr/index.php', [
                'pageTitle' => 'RH',
                'permissionDenied' => true,
            ]);
        }

        $repo = new LeaveRequestRepository();
        $leaveRequests = [];
        try {
            // Si approbateur => vue globale entreprise, sinon vue personnelle.
            $leaveRequests = $repo->listByCompany(
                companyId: $userContext->companyId,
                onlyUserId: $canApprove ? null : $userContext->userId,
            );
        } catch (\Throwable) {
            $leaveRequests = [];
        }

        return $this->renderPage('hr/index.php', [
            'pageTitle' => 'RH',
            'permissionDenied' => false,
            'canRequest' => $canRequest,
            'canApprove' => $canApprove,
            'csrfToken' => Csrf::token(),
            'leaveRequests' => $leaveRequests,
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
        ]);
    }

    public function newLeaveRequest(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('hr.leave.request', $userContext->permissions, true)) {
            return Response::redirect('hr');
        }

        $err = $request->getQueryParam('err', null);
        $errStr = is_string($err) && $err !== '' ? $err : null;

        return $this->renderPage('hr/new.php', [
            'pageTitle' => 'Nouvelle demande RH',
            'csrfToken' => Csrf::token(),
            'error' => $errStr,
        ]);
    }

    public function createLeaveRequest(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('hr.leave.request', $userContext->permissions, true)) {
            return Response::redirect('hr');
        }

        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('hr/leave/new?err=CSRF%20invalide');
        }

        $type = trim((string) $request->getBodyParam('type', 'conges'));
        $startDate = trim((string) $request->getBodyParam('start_date', ''));
        $endDate = trim((string) $request->getBodyParam('end_date', ''));
        $reason = trim((string) $request->getBodyParam('reason', ''));

        if ($startDate === '' || $endDate === '') {
            return Response::redirect('hr/leave/new?err=Dates%20requises');
        }
        if ($endDate < $startDate) {
            return Response::redirect('hr/leave/new?err=Date%20de%20fin%20invalide');
        }

        $repo = new LeaveRequestRepository();
        try {
            $repo->create(
                companyId: $userContext->companyId,
                userId: $userContext->userId,
                type: $type,
                startDateYmd: $startDate,
                endDateYmd: $endDate,
                reason: $reason !== '' ? $reason : null,
            );
        } catch (\Throwable) {
            return Response::redirect('hr/leave/new?err=Impossible%20de%20cr%C3%A9er%20la%20demande');
        }

        Csrf::rotate();
        return Response::redirect('hr?msg=Demande%20envoy%C3%A9e');
    }

    public function approveLeaveRequest(Request $request, UserContext $userContext): Response
    {
        if ($userContext->userId === null || $userContext->companyId === null) {
            return Response::redirect('login');
        }
        if (!in_array('hr.leave.approve', $userContext->permissions, true)) {
            return Response::redirect('hr');
        }

        $csrfToken = $request->getBodyParam('csrf_token', null);
        if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
            return Response::redirect('hr?err=CSRF%20invalide');
        }

        $leaveRequestIdRaw = $request->getBodyParam('leave_request_id', null);
        $leaveRequestId = is_numeric($leaveRequestIdRaw) ? (int) $leaveRequestIdRaw : 0;
        $status = trim((string) $request->getBodyParam('status', ''));
        $rejectionReason = trim((string) $request->getBodyParam('rejection_reason', ''));

        if ($leaveRequestId <= 0) {
            return Response::redirect('hr?err=Demande%20invalide');
        }
        if (!in_array($status, ['approved', 'rejected'], true)) {
            return Response::redirect('hr?err=Statut%20invalide');
        }

        $repo = new LeaveRequestRepository();
        try {
            $repo->setStatus(
                companyId: $userContext->companyId,
                leaveRequestId: $leaveRequestId,
                status: $status,
                approvedByUserId: $userContext->userId,
                rejectionReason: $rejectionReason !== '' ? $rejectionReason : null,
            );
        } catch (\Throwable) {
            return Response::redirect('hr?err=Impossible%20de%20mettre%20%C3%A0%20jour%20la%20demande');
        }

        Csrf::rotate();
        return Response::redirect('hr?msg=Demande%20mise%20%C3%A0%20jour');
    }
}

