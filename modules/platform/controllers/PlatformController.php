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
use Modules\Platform\Repositories\PlatformBillingSettingsRepository;
use Modules\Platform\Repositories\PlatformInvoiceRepository;
use Modules\Platform\Repositories\PackRepository;
use Modules\Rbac\Services\TenantRbacBootstrapService;
use Modules\Users\Repositories\UserAdminRepository;

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

        $tabRaw = (string) $request->getQueryParam('tab', 'companies');
        $tab = in_array($tabRaw, ['companies', 'packs', 'audit', 'invoices', 'users', 'settings'], true) ? $tabRaw : 'companies';

        $companies = [];
        $packs = [];
        $auditRows = [];
        $invoiceTracking = [];
        $platformUsers = [];
        $platformBillingSettings = [];
        try {
            $companies = (new CompanyRepository())->listTenantCompanies(500);
        } catch (\Throwable) {
        }
        if ($tab === 'packs') {
            try {
                $packs = (new PackRepository())->listAll();
            } catch (\Throwable) {
            }
        }
        if ($tab === 'audit' && $this->can($userContext, 'platform.audit.read')) {
            try {
                $auditRows = (new AuditLogRepository())->listRecent(250);
            } catch (\Throwable) {
            }
        }
        if ($tab === 'invoices' && $this->can($userContext, 'platform.billing.manage')) {
            try {
                $invoiceTracking = (new PlatformInvoiceRepository())->listCompanyInvoiceTracking(1000);
            } catch (\Throwable) {
            }
        }
        if ($tab === 'users') {
            try {
                $platformUsers = (new UserAdminRepository())->listUsersWithPlatformRole(500);
            } catch (\Throwable) {
            }
        }
        if ($tab === 'settings') {
            try {
                $platformBillingSettings = (new PlatformBillingSettingsRepository())->get();
            } catch (\Throwable) {
            }
        }

        return $this->renderPage('platform/companies_index.php', [
            'pageTitle' => 'Plateforme — Sociétés',
            'platformTab' => $tab,
            'companies' => $companies,
            'packs' => $packs,
            'auditRows' => $auditRows,
            'invoiceTracking' => $invoiceTracking,
            'platformUsers' => $platformUsers,
            'platformBillingSettings' => $platformBillingSettings,
            'csrfToken' => Csrf::token(),
            'canAudit' => $this->can($userContext, 'platform.audit.read'),
            'canBilling' => $this->can($userContext, 'platform.billing.manage'),
            'canImpersonate' => $this->can($userContext, 'platform.impersonate.start'),
            'currentUserId' => (int) ($userContext->userId ?? 0),
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
        ]);
    }

    public function platformBillingSave(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext) || !$this->can($userContext, 'platform.company.manage')) {
            return Response::redirect('dashboard');
        }
        if (!Csrf::verify($request->getBodyParam('csrf_token', null))) {
            return Response::redirect('platform/companies?tab=settings&err=CSRF%20invalide');
        }
        $existing = (new PlatformBillingSettingsRepository())->get();
        $stripeInput = trim((string) $request->getBodyParam('stripe_secret_key', ''));
        $data = [
            'legal_name' => trim((string) $request->getBodyParam('legal_name', '')),
            'address' => trim((string) $request->getBodyParam('address', '')),
            'siret' => trim((string) $request->getBodyParam('siret', '')),
            'rib' => trim((string) $request->getBodyParam('rib', '')),
            'phone' => trim((string) $request->getBodyParam('phone', '')),
            'email' => trim((string) $request->getBodyParam('email', '')),
            'website' => trim((string) $request->getBodyParam('website', '')),
            'stripe_secret_key' => $stripeInput !== '' ? $stripeInput : (string) ($existing['stripe_secret_key'] ?? ''),
        ];
        try {
            (new PlatformBillingSettingsRepository())->save($data);
        } catch (\Throwable) {
            return Response::redirect('platform/companies?tab=settings&err=Enregistrement%20impossible');
        }
        Csrf::rotate();

        return Response::redirect('platform/companies?tab=settings&msg=Param%C3%A8tres%20enregistr%C3%A9s');
    }

    public function companyNew(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext) || !$this->can($userContext, 'platform.company.manage')) {
            return Response::redirect('dashboard');
        }

        $packs = [];
        try {
            $packs = (new PackRepository())->listAll();
        } catch (\Throwable) {
            $packs = [];
        }

        return $this->renderPage('platform/company_new.php', [
            'pageTitle' => 'Nouvelle société',
            'error' => null,
            'csrfToken' => Csrf::token(),
            'packs' => $packs,
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
        $packId = (int) $request->getBodyParam('pack_id', 0);
        $initialUserEmail = trim((string) $request->getBodyParam('initial_user_email', ''));
        $initialUserName = trim((string) $request->getBodyParam('initial_user_name', ''));
        $initialUserPassword = (string) $request->getBodyParam('initial_user_password', '');
        if ($name === '') {
            $packs = [];
            try { $packs = (new PackRepository())->listAll(); } catch (\Throwable) {}
            return $this->renderPage('platform/company_new.php', [
                'pageTitle' => 'Nouvelle société',
                'error' => 'Le nom est obligatoire.',
                'csrfToken' => Csrf::token(),
                'packs' => $packs,
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

            $selectedPack = null;
            $allPacks = (new PackRepository())->listAll();
            foreach ($allPacks as $p) {
                if ((int) ($p['id'] ?? 0) === $packId) {
                    $selectedPack = $p;
                    break;
                }
            }
            if (!is_array($selectedPack)) {
                throw new \RuntimeException('Pack obligatoire.');
            }
            $packName = trim((string) ($selectedPack['name'] ?? ''));
            $packPrice = (float) ($selectedPack['price'] ?? 0);
            $packSeats = max(0, (int) ($selectedPack['maxUsers'] ?? 0));
            $renewDate = null;
            $billingStatus = 'active';
            if ($packPrice <= 0) {
                $billingStatus = 'trial';
                $renewDate = (new \DateTimeImmutable('today'))->modify('+7 days')->format('Y-m-d');
            }
            $repo->updateBilling($newId, [
                'billingPlan' => $packName,
                'billingStatus' => $billingStatus,
                'billingCycle' => null,
                'maxSeats' => $packSeats,
                'subscriptionRenewsAt' => $renewDate,
                'externalBillingRef' => null,
            ]);

            $rbacBootstrap = new TenantRbacBootstrapService();
            $rbacBootstrap->bootstrapCompany($newId);

            if ($initialUserEmail !== '') {
                if (!filter_var($initialUserEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($initialUserPassword) < 8) {
                    throw new \RuntimeException('Utilisateur initial invalide.');
                }
                $newUserId = (new UserAdminRepository())->createBasicUser(
                    companyId: $newId,
                    email: $initialUserEmail,
                    password: $initialUserPassword,
                    fullName: $initialUserName !== '' ? $initialUserName : 'Administrateur',
                );
                // Utilisateur principal : tous les rôles tenant (accès complet métier + profils cumulés).
                $rbacBootstrap->assignUserAllTenantRoles($newId, $newUserId);
            }
            $this->audit($homeId, $actorId, 'platform.company.create', $newId, ['name' => $name]);
        } catch (\Throwable) {
            $packs = [];
            try { $packs = (new PackRepository())->listAll(); } catch (\Throwable) {}
            return $this->renderPage('platform/company_new.php', [
                'pageTitle' => 'Nouvelle société',
                'error' => 'Création impossible (erreur serveur).',
                'csrfToken' => Csrf::token(),
                'packs' => $packs,
            ]);
        }

        return Response::redirect('platform/companies/show?id=' . $newId);
    }

    public function companyShow(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext)) {
            return Response::redirect('dashboard');
        }

        $canManage = $this->can($userContext, 'platform.company.manage');
        $id = (int) $request->getQueryParam('id', 0);
        $homeCompanyId = (int) ($userContext->homeCompanyId() ?? 0);
        if (!$canManage) {
            $id = $homeCompanyId;
        }
        if ($id <= 0) {
            return Response::redirect('dashboard');
        }

        $row = (new CompanyRepository())->findById($id);
        if ($row === null) {
            return Response::redirect($canManage ? 'platform/companies' : 'dashboard');
        }

        $tabRaw = (string) $request->getQueryParam('tab', 'general');
        $tab = in_array($tabRaw, ['general', 'billing', 'users'], true) ? $tabRaw : 'general';
        $users = [];
        try {
            $users = (new UserAdminRepository())->listBasicByCompanyId($id);
        } catch (\Throwable) {
        }

        return $this->renderPage('platform/company_show.php', [
            'pageTitle' => 'Société #' . $id,
            'company' => $row,
            'csrfToken' => Csrf::token(),
            'companyTab' => $tab,
            'companyUsers' => $users,
            'canBilling' => $this->can($userContext, 'platform.billing.manage'),
            'canImpersonate' => $canManage && $this->can($userContext, 'platform.impersonate.start'),
            'canManageCompany' => $canManage,
            'flashMessage' => $request->getQueryParam('msg', null),
            'flashError' => $request->getQueryParam('err', null),
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
        $billingCycle = trim((string) $request->getBodyParam('billing_cycle', ''));
        $maxSeatsRaw = $request->getBodyParam('max_seats', '');
        $externalBillingRef = trim((string) $request->getBodyParam('external_billing_ref', ''));

        $maxSeats = null;
        if ($maxSeatsRaw !== '' && $maxSeatsRaw !== null) {
            $maxSeats = max(0, (int) $maxSeatsRaw);
        }

        if ($billingStatus !== '' && !in_array($billingStatus, self::BILLING_STATUSES, true)) {
            $billingStatus = '';
        }
        if (!in_array($billingCycle, ['monthly', 'annual'], true)) {
            $billingCycle = '';
        }

        $existing = $repo->findById($id);
        $nextRenewDate = isset($existing['subscriptionRenewsAt']) ? (string) $existing['subscriptionRenewsAt'] : '';
        $oldCycle = isset($existing['billingCycle']) ? (string) $existing['billingCycle'] : '';
        if ($billingCycle !== '' && ($nextRenewDate === '' || $oldCycle !== $billingCycle)) {
            $base = new \DateTimeImmutable('today');
            $nextRenewDate = $billingCycle === 'annual'
                ? $base->modify('+1 year')->format('Y-m-d')
                : $base->modify('+1 month')->format('Y-m-d');
        }

        $homeId = (int) $userContext->homeCompanyId();
        $actorId = (int) $userContext->userId;

        try {
            $repo->updateBilling($id, [
                'billingPlan' => $billingPlan,
                'billingStatus' => $billingStatus,
                'billingCycle' => $billingCycle !== '' ? $billingCycle : $oldCycle,
                'maxSeats' => $maxSeats,
                'subscriptionRenewsAt' => $nextRenewDate !== '' ? $nextRenewDate : null,
                'externalBillingRef' => $externalBillingRef,
            ]);
            $this->audit($homeId, $actorId, 'platform.billing.update', $id, [
                'billingPlan' => $billingPlan,
                'billingStatus' => $billingStatus,
                'billingCycle' => $billingCycle !== '' ? $billingCycle : $oldCycle,
            ]);
        } catch (\Throwable) {
        }

        return Response::redirect('platform/companies/show?id=' . $id);
    }

    public function packsUpsert(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext) || !$this->can($userContext, 'platform.billing.manage')) {
            return Response::redirect('dashboard');
        }
        if (!Csrf::verify($request->getBodyParam('csrf_token', null))) {
            return Response::redirect('platform/companies?tab=packs');
        }

        $id = (int) $request->getBodyParam('id', 0);
        $name = trim((string) $request->getBodyParam('name', ''));
        $maxUsers = max(0, (int) $request->getBodyParam('max_users', 0));
        $price = max(0.0, (float) $request->getBodyParam('price', 0));
        if ($name === '') {
            return Response::redirect('platform/packs/new?err=Nom%20du%20pack%20requis');
        }

        try {
            (new PackRepository())->upsert([
                'id' => $id > 0 ? $id : null,
                'name' => $name,
                'maxUsers' => $maxUsers,
                'price' => round($price, 2),
            ]);
            $this->audit((int) $userContext->homeCompanyId(), (int) $userContext->userId, 'platform.pack.upsert', null, ['name' => $name]);
        } catch (\Throwable) {
            return Response::redirect('platform/packs/new?err=Impossible%20d%27enregistrer%20le%20pack');
        }
        return Response::redirect('platform/companies?tab=packs&msg=Pack%20enregistre');
    }

    public function packsNew(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext) || !$this->can($userContext, 'platform.billing.manage')) {
            return Response::redirect('dashboard');
        }

        $err = $request->getQueryParam('err', null);
        $error = is_string($err) && $err !== '' ? $err : null;

        return $this->renderPage('platform/pack_new.php', [
            'pageTitle' => 'Nouveau pack',
            'csrfToken' => Csrf::token(),
            'error' => $error,
        ]);
    }

    public function packsDelete(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext) || !$this->can($userContext, 'platform.billing.manage')) {
            return Response::redirect('dashboard');
        }
        if (!Csrf::verify($request->getBodyParam('csrf_token', null))) {
            return Response::redirect('platform/companies?tab=packs');
        }
        $id = (int) $request->getBodyParam('id', 0);
        if ($id > 0) {
            (new PackRepository())->delete($id);
            $this->audit((int) $userContext->homeCompanyId(), (int) $userContext->userId, 'platform.pack.delete', null, ['id' => $id]);
        }
        return Response::redirect('platform/companies?tab=packs&msg=Pack%20supprime');
    }

    public function companyUserCreate(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext) || !$this->can($userContext, 'platform.company.manage')) {
            return Response::redirect('dashboard');
        }
        if (!Csrf::verify($request->getBodyParam('csrf_token', null))) {
            return Response::redirect('platform/companies');
        }
        $companyId = (int) $request->getBodyParam('company_id', 0);
        $email = trim((string) $request->getBodyParam('email', ''));
        $fullName = trim((string) $request->getBodyParam('full_name', ''));
        $password = (string) $request->getBodyParam('password', '');
        $roleIdsRaw = $request->getBodyParam('role_ids', []);
        if (!is_array($roleIdsRaw)) {
            $roleIdsRaw = $roleIdsRaw !== null && $roleIdsRaw !== '' ? [(int) $roleIdsRaw] : [];
        }
        $roleIds = array_values(array_filter(array_map(static fn ($v) => (int) $v, $roleIdsRaw), static fn (int $id) => $id > 0));
        if ($companyId <= 0) {
            return Response::redirect('platform/companies');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 8) {
            return Response::redirect('platform/companies/users/new?company_id=' . $companyId . '&err=Utilisateur%20invalide');
        }
        try {
            $rbacBootstrap = new TenantRbacBootstrapService();
            $rbacBootstrap->bootstrapCompany($companyId);

            if ($roleIds === []) {
                $adminId = $rbacBootstrap->getTenantAdminRoleId($companyId);
                if ($adminId !== null && $adminId > 0) {
                    $roleIds = [$adminId];
                } else {
                    $availableRoles = (new UserAdminRepository())->listRoleIdsByCompanyId($companyId);
                    foreach ($availableRoles as $ar) {
                        $rid = (int) ($ar['id'] ?? 0);
                        if ($rid > 0) {
                            $roleIds[] = $rid;
                        }
                    }
                }
            }
            if ($roleIds === []) {
                throw new \RuntimeException('Aucun rôle disponible pour cette société.');
            }
            (new UserAdminRepository())->createUserWithRoles(
                companyId: $companyId,
                email: $email,
                password: $password,
                fullName: $fullName !== '' ? $fullName : 'Utilisateur',
                roleIds: $roleIds,
            );
            $this->audit((int) $userContext->homeCompanyId(), (int) $userContext->userId, 'platform.company.user.create', $companyId, ['email' => $email]);
        } catch (\Throwable $e) {
            return Response::redirect('platform/companies/users/new?company_id=' . $companyId . '&err=' . rawurlencode($e->getMessage() ?: 'Creation impossible'));
        }
        return Response::redirect('platform/companies/show?id=' . $companyId . '&tab=users&msg=Utilisateur%20cree');
    }

    public function companyUserNew(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext) || !$this->can($userContext, 'platform.company.manage')) {
            return Response::redirect('dashboard');
        }
        $companyId = (int) $request->getQueryParam('company_id', 0);
        if ($companyId <= 0) {
            return Response::redirect('platform/companies');
        }
        $company = (new CompanyRepository())->findById($companyId);
        if (!is_array($company)) {
            return Response::redirect('platform/companies');
        }
        try {
            (new TenantRbacBootstrapService())->bootstrapCompany($companyId);
        } catch (\Throwable) {
            // ignore
        }
        $roles = [];
        try {
            $roles = (new UserAdminRepository())->listRoleIdsByCompanyId($companyId);
        } catch (\Throwable) {
            $roles = [];
        }
        $err = $request->getQueryParam('err', null);
        $error = is_string($err) && $err !== '' ? $err : null;

        return $this->renderPage('platform/company_user_new.php', [
            'pageTitle' => 'Nouvel utilisateur société',
            'csrfToken' => Csrf::token(),
            'company' => $company,
            'roles' => $roles,
            'error' => $error,
        ]);
    }

    public function companyUserDelete(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext) || !$this->can($userContext, 'platform.company.manage')) {
            return Response::redirect('dashboard');
        }
        if (!Csrf::verify($request->getBodyParam('csrf_token', null))) {
            return Response::redirect('platform/companies');
        }
        $companyId = (int) $request->getBodyParam('company_id', 0);
        $userId = (int) $request->getBodyParam('user_id', 0);
        if ($companyId > 0 && $userId > 0) {
            (new UserAdminRepository())->deleteByCompanyAndUserId($companyId, $userId);
            $this->audit((int) $userContext->homeCompanyId(), (int) $userContext->userId, 'platform.company.user.delete', $companyId, ['userId' => $userId]);
        }
        return Response::redirect('platform/companies/show?id=' . $companyId . '&tab=users&msg=Utilisateur%20supprime');
    }

    public function companyUserUpdate(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext) || !$this->can($userContext, 'platform.company.manage')) {
            return Response::redirect('dashboard');
        }
        if (!Csrf::verify($request->getBodyParam('csrf_token', null))) {
            return Response::redirect('platform/companies');
        }
        $companyId = (int) $request->getBodyParam('company_id', 0);
        $userId = (int) $request->getBodyParam('user_id', 0);
        $fullName = trim((string) $request->getBodyParam('full_name', ''));
        $email = trim((string) $request->getBodyParam('email', ''));
        $status = trim((string) $request->getBodyParam('status', 'active'));
        if ($companyId <= 0 || $userId <= 0 || $fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Response::redirect('platform/companies/show?id=' . $companyId . '&tab=users&err=Donnees%20utilisateur%20invalides');
        }
        try {
            (new UserAdminRepository())->updateBasicByCompanyAndUserId($companyId, $userId, $fullName, $email, $status);
            $this->audit((int) $userContext->homeCompanyId(), (int) $userContext->userId, 'platform.company.user.update', $companyId, ['userId' => $userId]);
        } catch (\Throwable) {
            return Response::redirect('platform/companies/show?id=' . $companyId . '&tab=users&err=Modification%20impossible');
        }
        return Response::redirect('platform/companies/show?id=' . $companyId . '&tab=users&msg=Utilisateur%20modifie');
    }

    public function platformUserCreate(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext) || !$this->can($userContext, 'platform.company.manage')) {
            return Response::redirect('dashboard');
        }
        if (!Csrf::verify($request->getBodyParam('csrf_token', null))) {
            return Response::redirect('platform/companies?tab=users&err=Requete%20invalide');
        }
        $email = trim((string) $request->getBodyParam('email', ''));
        $fullName = trim((string) $request->getBodyParam('full_name', ''));
        $password = (string) $request->getBodyParam('password', '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 8) {
            return Response::redirect('platform/users/new?err=Utilisateur%20invalide');
        }
        try {
            $platformCompanyId = (new CompanyRepository())->ensurePlatformOperatorCompany();
            $newUserId = (new UserAdminRepository())->createBasicUser($platformCompanyId, $email, $password, $fullName !== '' ? $fullName : 'Utilisateur');
            (new UserAdminRepository())->assignPlatformOperatorRole($platformCompanyId, $newUserId);
            $this->audit((int) $userContext->homeCompanyId(), (int) $userContext->userId, 'platform.user.create', $platformCompanyId, ['email' => $email]);
        } catch (\Throwable $e) {
            return Response::redirect('platform/users/new?err=' . rawurlencode($e->getMessage() ?: 'Creation impossible'));
        }
        return Response::redirect('platform/companies?tab=users&msg=Utilisateur%20cree');
    }

    public function platformUserNew(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext) || !$this->can($userContext, 'platform.company.manage')) {
            return Response::redirect('dashboard');
        }
        $err = $request->getQueryParam('err', null);
        $error = is_string($err) && $err !== '' ? $err : null;
        return $this->renderPage('platform/platform_user_new.php', [
            'pageTitle' => 'Nouvel utilisateur plateforme',
            'csrfToken' => Csrf::token(),
            'error' => $error,
        ]);
    }

    public function platformUserUpdate(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext) || !$this->can($userContext, 'platform.company.manage')) {
            return Response::redirect('dashboard');
        }
        if (!Csrf::verify($request->getBodyParam('csrf_token', null))) {
            return Response::redirect('platform/companies?tab=users&err=Requete%20invalide');
        }
        $userId = (int) $request->getBodyParam('user_id', 0);
        $fullName = trim((string) $request->getBodyParam('full_name', ''));
        $email = trim((string) $request->getBodyParam('email', ''));
        $status = trim((string) $request->getBodyParam('status', 'active'));
        if ($userId <= 0 || $fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Response::redirect('platform/users/edit?id=' . $userId . '&err=Donnees%20utilisateur%20invalides');
        }
        $repoUsers = new UserAdminRepository();
        if (!$repoUsers->userHasPlatformRole($userId)) {
            return Response::redirect('platform/companies?tab=users&err=Utilisateur%20non%20back-office');
        }
        $targetUser = $repoUsers->findById($userId);
        if ($targetUser === null) {
            return Response::redirect('platform/companies?tab=users&err=Utilisateur%20introuvable');
        }
        $userCompanyId = (int) ($targetUser['companyId'] ?? 0);
        if ($userId === (int) $userContext->userId && in_array($status, ['inactive', 'disabled'], true)) {
            return Response::redirect('platform/users/edit?id=' . $userId . '&err=Impossible%20de%20desactiver%20votre%20propre%20compte');
        }
        try {
            $repoUsers->updateBasicByCompanyAndUserId($userCompanyId, $userId, $fullName, $email, $status);
            $this->audit((int) $userContext->homeCompanyId(), (int) $userContext->userId, 'platform.user.update', $userCompanyId, ['userId' => $userId]);
        } catch (\Throwable) {
            return Response::redirect('platform/users/edit?id=' . $userId . '&err=Modification%20impossible');
        }
        return Response::redirect('platform/companies?tab=users&msg=Utilisateur%20modifie');
    }

    public function platformUserEdit(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext) || !$this->can($userContext, 'platform.company.manage')) {
            return Response::redirect('dashboard');
        }
        $userId = (int) $request->getQueryParam('id', 0);
        if ($userId <= 0) {
            return Response::redirect('platform/companies?tab=users');
        }
        $user = null;
        try {
            foreach ((new UserAdminRepository())->listUsersWithPlatformRole(500) as $u) {
                if ((int) ($u['id'] ?? 0) === $userId) {
                    $user = $u;
                    break;
                }
            }
        } catch (\Throwable) {
            $user = null;
        }
        if (!is_array($user)) {
            return Response::redirect('platform/companies?tab=users&err=Utilisateur%20introuvable');
        }
        $err = $request->getQueryParam('err', null);
        $error = is_string($err) && $err !== '' ? $err : null;
        return $this->renderPage('platform/platform_user_edit.php', [
            'pageTitle' => 'Modifier utilisateur plateforme',
            'csrfToken' => Csrf::token(),
            'user' => $user,
            'error' => $error,
            'currentUserId' => (int) $userContext->userId,
        ]);
    }

    public function platformUserDelete(Request $request, UserContext $userContext): Response
    {
        if (!$this->assertPlatform($userContext) || !$this->can($userContext, 'platform.company.manage')) {
            return Response::redirect('dashboard');
        }
        if (!Csrf::verify($request->getBodyParam('csrf_token', null))) {
            return Response::redirect('platform/companies?tab=users&err=Requete%20invalide');
        }
        $userId = (int) $request->getBodyParam('user_id', 0);
        if ($userId <= 0) {
            return Response::redirect('platform/companies?tab=users');
        }
        if ($userId === (int) $userContext->userId) {
            return Response::redirect('platform/companies?tab=users&err=Suppression%20de%20votre%20compte%20interdite');
        }
        $repoUsers = new UserAdminRepository();
        if (!$repoUsers->userHasPlatformRole($userId)) {
            return Response::redirect('platform/companies?tab=users&err=Suppression%20reservee%20aux%20comptes%20back-office');
        }
        $targetUser = $repoUsers->findById($userId);
        if ($targetUser === null) {
            return Response::redirect('platform/companies?tab=users&err=Utilisateur%20introuvable');
        }
        $userCompanyId = (int) ($targetUser['companyId'] ?? 0);
        try {
            $repoUsers->deleteByCompanyAndUserId($userCompanyId, $userId);
            $this->audit((int) $userContext->homeCompanyId(), (int) $userContext->userId, 'platform.user.delete', $userCompanyId, ['userId' => $userId]);
        } catch (\Throwable) {
            return Response::redirect('platform/companies?tab=users&err=Suppression%20impossible');
        }
        return Response::redirect('platform/companies?tab=users&msg=Utilisateur%20supprime');
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
