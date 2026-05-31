<?php
declare(strict_types=1);

namespace Modules\Signup\Services;

use Modules\Companies\Repositories\CompanyRepository;
use Modules\Platform\Services\PlatformMailService;
use Modules\Rbac\Services\TenantRbacBootstrapService;
use Modules\Users\Repositories\UserAdminRepository;
use Core\Config;

/**
 * Création d'un espace tenant (société + admin) — même logique que le back-office plateforme.
 */
final class TenantProvisioningService
{
    /**
     * @param array{
     *   company_name:string,
     *   company_siret?:string,
     *   company_address?:string,
     *   company_billing_address?:string,
     *   billing_email:?string,
     *   pack:array,
     *   billing_cycle:string,
     *   user_email:string,
     *   password_hash:string,
     *   full_name:string,
     *   external_billing_ref:?string
     * } $input
     * @return array{companyId:int, userId:int}
     */
    public function provision(array $input): array
    {
        $companyName = trim((string) ($input['company_name'] ?? ''));
        if ($companyName === '') {
            throw new \InvalidArgumentException('Nom de société requis.');
        }

        $pack = $input['pack'] ?? [];
        if (!is_array($pack)) {
            throw new \InvalidArgumentException('Pack invalide.');
        }

        $packName = trim((string) ($pack['name'] ?? ''));
        $packPrice = (float) ($pack['price'] ?? 0);
        $packSeats = max(0, (int) ($pack['maxUsers'] ?? 0));
        $billingCycle = trim((string) ($input['billing_cycle'] ?? 'monthly'));
        if (!in_array($billingCycle, ['monthly', 'annual'], true)) {
            $billingCycle = 'monthly';
        }

        $userEmail = trim((string) ($input['user_email'] ?? ''));
        $passwordHash = (string) ($input['password_hash'] ?? '');
        $fullName = trim((string) ($input['full_name'] ?? ''));
        if ($userEmail === '' || $passwordHash === '') {
            throw new \InvalidArgumentException('Utilisateur invalide.');
        }

        $billingEmail = trim((string) ($input['billing_email'] ?? ''));
        if ($billingEmail === '') {
            $billingEmail = $userEmail;
        }

        $repo = new CompanyRepository();
        $companyId = $repo->create([
            'name' => $companyName,
            'siret' => trim((string) ($input['company_siret'] ?? '')),
            'address' => trim((string) ($input['company_address'] ?? '')),
            'billingAddress' => trim((string) ($input['company_billing_address'] ?? '')),
            'billingEmail' => $billingEmail,
            'status' => 'active',
        ]);

        $startDate = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $renewDate = null;
        $billingStatus = 'active';
        $cycleForDb = null;

        if ($packPrice <= 0) {
            $billingStatus = 'trial';
            $renewDate = (new \DateTimeImmutable('today'))->modify('+7 days')->format('Y-m-d');
        } else {
            $billingStatus = 'active';
            $cycleForDb = $billingCycle;
            $base = new \DateTimeImmutable('today');
            $renewDate = $billingCycle === 'annual'
                ? $base->modify('+1 year')->format('Y-m-d')
                : $base->modify('+1 month')->format('Y-m-d');
        }

        $repo->updateBilling($companyId, [
            'billingPlan' => $packName,
            'billingStatus' => $billingStatus,
            'billingCycle' => $cycleForDb,
            'maxSeats' => $packSeats,
            'subscriptionStartedAt' => $startDate,
            'subscriptionRenewsAt' => $renewDate,
            'externalBillingRef' => $input['external_billing_ref'] ?? null,
        ]);

        $rbac = new TenantRbacBootstrapService();
        $rbac->bootstrapCompany($companyId);

        $userId = (new UserAdminRepository())->createBasicUser(
            companyId: $companyId,
            email: $userEmail,
            password: bin2hex(random_bytes(16)),
            fullName: $fullName !== '' ? $fullName : 'Administrateur',
        );

        $pdo = \Core\Database\Connection::pdo();
        $pdo->prepare('UPDATE `User` SET passwordHash = :hash WHERE id = :id AND companyId = :cid')
            ->execute(['hash' => $passwordHash, 'id' => $userId, 'cid' => $companyId]);

        $rbac->assignUserAllTenantRoles($companyId, $userId);

        try {
            $appUrl = rtrim((string) (Config::env('APP_URL', '') ?? ''), '/');
            $loginUrl = $appUrl !== '' ? $appUrl . '/login' : '/login';
            (new PlatformMailService())->sendCompanyWelcome($userEmail, $companyName, $userEmail, $loginUrl);
        } catch (\Throwable) {
        }

        return ['companyId' => $companyId, 'userId' => $userId];
    }
}
