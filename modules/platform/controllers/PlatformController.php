<?php
declare(strict_types=1);

namespace Modules\Platform\Controllers;

use App\Controllers\BaseController;
use Core\Context\UserContext;
use Core\Http\ClientInfo;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\Csrf;
use Modules\Companies\Repositories\CompanyRepository;
use Modules\Platform\Repositories\AuditLogRepository;

final class PlatformController extends BaseController
{
    private const STATUSES = ['active', 'suspended', 'disabled'];
    private const BILLING_STATUSES = ['trial', 'active', 'past_due', 'cancelled'];

    public function companiesIndex(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext)) {
            return Response::redirect('dashboard');
        }
        if (!$this->can($userContext, 'platform.company.manage')) {
            return Response::redirect('dashboard');
        }

        $companies = [];
        try {
            $companies = (new CompanyRepository())->listAll(500);
        } catch (\Throwable) {
        }

        return $this->renderPage('platform/companies_index.php', [
            'pageTitle' => 'Plateforme — Sociétés',
            'companies' => $companies,
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function companyNew(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext) || !$this->can($userContext, 'platform.company.manage')) {
            return Response::redirect('dashboard');
        }

        return $this->renderPage('platform/company_new.php', [
            'pageTitle' => 'Nouvelle société',
            'error' => null,
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function companyCreate(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext) || !$this->can($userContext, 'platform.company.manage')) {
            return Response::redirect('dashboard');
        }

        if (!Csrf::verify($request->getBodyParam('csrf_token', null))) {
            return $this->renderPage('platform/company_new.php', [
                'pageTitle' => 'Nouvelle société',
                'error' => 'Requête invalide (CSRF).',
                'csrfToken' => Csrf::token(),
            ]);
        }

        $name = trim((string) $request->getBodyParam('name', ''));
        $billingEmail = trim((string) $request->getBodyParam('billing_email', ''));
        $status = trim((string) $request->getBodyParam('status', 'active'));
        if ($name === '') {
            return $this->renderPage('platform/company_new.php', [
                'pageTitle' => 'Nouvelle société',
                'error' => 'Le nom est obligatoire.',
                'csrfToken' => Csrf::token(),
            ]);
        }
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'active';
        }

        $homeId = (int) $userContext->homeCompanyId();
        $actorId = (int) $userContext->userId;

        try {
            $repo = new CompanyRepository();
            $newId = $repo->create([
                'name' => $name,
                'billingEmail' => $billingEmail !== '' ? $billingEmail : null,
                'status' => $status,
            ]);
            $this->audit($homeId, $actorId, 'platform.company.create', $newId, ['name' => $name]);
        } catch (\Throwable) {
            return $this->renderPage('platform/company_new.php', [
                'pageTitle' => 'Nouvelle société',
                'error' => 'Création impossible (erreur serveur).',
                'csrfToken' => Csrf::token(),
            ]);
        }

        return Response::redirect('platform/companies/show?id=' . $newId);
    }

    public function companyShow(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext) || !$this->can($userContext, 'platform.company.manage')) {
            return Response::redirect('dashboard');
        }

        $id = (int) $request->getQueryParam('id', 0);
        if ($id <= 0) {
            return Response::redirect('platform/companies');
        }

        $row = (new CompanyRepository())->findById($id);
        if ($row === null) {
            return Response::redirect('platform/companies');
        }

        return $this->renderPage('platform/company_show.php', [
            'pageTitle' => 'Société #' . $id,
            'company' => $row,
            'csrfToken' => Csrf::token(),
            'canBilling' => $this->can($userContext, 'platform.billing.manage'),
            'canImpersonate' => $this->can($userContext, 'platform.impersonate.start'),
        ]);
    }

    public function companyUpdate(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext) || !$this->can($userContext, 'platform.company.manage')) {
            return Response::redirect('dashboard');
        }

        if (!Csrf::verify($request->getBodyParam('csrf_token', null))) {
            return Response::redirect('platform/companies');
        }

        $id = (int) $request->getBodyParam('id', 0);
        if ($id <= 0) {
            return Response::redirect('platform/companies');
        }

        $repo = new CompanyRepository();
        if ($repo->findById($id) === null) {
            return Response::redirect('platform/companies');
        }

        $name = trim((string) $request->getBodyParam('name', ''));
        $billingEmail = trim((string) $request->getBodyParam('billing_email', ''));
        $status = trim((string) $request->getBodyParam('status', 'active'));
        if ($name === '') {
            return Response::redirect('platform/companies/show?id=' . $id);
        }
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'active';
        }

        $homeId = (int) $userContext->homeCompanyId();
        $actorId = (int) $userContext->userId;

        try {
            $repo->updateCore($id, [
                'name' => $name,
                'billingEmail' => $billingEmail,
                'status' => $status,
            ]);
            $this->audit($homeId, $actorId, 'platform.company.update', $id, ['name' => $name, 'status' => $status]);
        } catch (\Throwable) {
        }

        return Response::redirect('platform/companies/show?id=' . $id);
    }

    public function billingUpdate(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext) || !$this->can($userContext, 'platform.billing.manage')) {
            return Response::redirect('dashboard');
        }

        if (!Csrf::verify($request->getBodyParam('csrf_token', null))) {
            return Response::redirect('platform/companies');
        }

        $id = (int) $request->getBodyParam('id', 0);
        if ($id <= 0) {
            return Response::redirect('platform/companies');
        }

        $repo = new CompanyRepository();
        if ($repo->findById($id) === null) {
            return Response::redirect('platform/companies');
        }

        $billingPlan = trim((string) $request->getBodyParam('billing_plan', ''));
        $billingStatus = trim((string) $request->getBodyParam('billing_status', ''));
        $maxSeatsRaw = $request->getBodyParam('max_seats', '');
        $subscriptionRenewsAt = trim((string) $request->getBodyParam('subscription_renews_at', ''));
        $externalBillingRef = trim((string) $request->getBodyParam('external_billing_ref', ''));

        $maxSeats = null;
        if ($maxSeatsRaw !== '' && $maxSeatsRaw !== null) {
            $maxSeats = max(0, (int) $maxSeatsRaw);
        }

        if ($billingStatus !== '' && !in_array($billingStatus, self::BILLING_STATUSES, true)) {
            $billingStatus = '';
        }

        $homeId = (int) $userContext->homeCompanyId();
        $actorId = (int) $userContext->userId;

        try {
            $repo->updateBilling($id, [
                'billingPlan' => $billingPlan,
                'billingStatus' => $billingStatus,
                'maxSeats' => $maxSeats,
                'subscriptionRenewsAt' => $subscriptionRenewsAt,
                'externalBillingRef' => $externalBillingRef,
            ]);
            $this->audit($homeId, $actorId, 'platform.billing.update', $id, [
                'billingPlan' => $billingPlan,
                'billingStatus' => $billingStatus,
            ]);
        } catch (\Throwable) {
        }

        return Response::redirect('platform/companies/show?id=' . $id);
    }

    public function auditIndex(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext) || !$this->can($userContext, 'platform.audit.read')) {
            return Response::redirect('dashboard');
        }

        $rows = [];
        try {
            $rows = (new AuditLogRepository())->listRecent(250);
        } catch (\Throwable) {
        }

        return $this->renderPage('platform/audit_index.php', [
            'pageTitle' => 'Journal d’audit plateforme',
            'auditRows' => $rows,
        ]);
    }

    public function impersonateStart(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext) || !$this->can($userContext, 'platform.impersonate.start')) {
            return Response::redirect('dashboard');
        }

        if (!Csrf::verify($request->getBodyParam('csrf_token', null))) {
            return Response::redirect('platform/companies');
        }

        $id = (int) $request->getBodyParam('company_id', 0);
        if ($id <= 0 || (new CompanyRepository())->findById($id) === null) {
            return Response::redirect('platform/companies');
        }

        $homeId = (int) $userContext->homeCompanyId();
        if ($id === $homeId) {
            unset($_SESSION['impersonate_target_company_id']);
            return Response::redirect('dashboard');
        }

        $_SESSION['impersonate_target_company_id'] = $id;
        $this->audit($homeId, (int) $userContext->userId, 'platform.impersonate.start', $id, []);

        return Response::redirect('dashboard');
    }

    public function impersonateStop(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext) || !$this->can($userContext, 'platform.impersonate.start')) {
            return Response::redirect('dashboard');
        }

        if (!Csrf::verify($request->getBodyParam('csrf_token', null))) {
            return Response::redirect('dashboard');
        }

        $homeId = (int) $userContext->homeCompanyId();
        $prev = isset($_SESSION['impersonate_target_company_id']) ? (int) $_SESSION['impersonate_target_company_id'] : 0;
        unset($_SESSION['impersonate_target_company_id']);
        if ($prev > 0) {
            $this->audit($homeId, (int) $userContext->userId, 'platform.impersonate.stop', $prev, []);
        }

        return Response::redirect('platform/companies');
    }

    private function assertPlatform(UserContext $userContext): bool
    {
        return $userContext->userId !== null && $userContext->homeCompanyId() !== null;
    }

    private function can(UserContext $userContext, string $code): bool
    {
        return in_array($code, $userContext->permissions, true);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function audit(int $homeCompanyId, int $actorUserId, string $action, ?int $targetCompanyId, array $metadata): void
    {
        try {
            (new AuditLogRepository())->insert(
                companyId: $homeCompanyId,
                actorUserId: $actorUserId,
                action: $action,
                targetCompanyId: $targetCompanyId,
                metadata: $metadata !== [] ? $metadata : null,
                ipAddress: ClientInfo::ipAddress(),
                userAgent: ClientInfo::userAgent(),
            );
        } catch (\Throwable) {
        }
    }
}
