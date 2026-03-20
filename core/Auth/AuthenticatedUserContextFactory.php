<?php
declare(strict_types=1);

namespace Core\Auth;

use Core\Config;
use Core\Context\UserContext;
use Core\Http\ClientInfo;
use Modules\Rbac\Repositories\RbacRepository;
use Modules\Rbac\Services\RbacService;
use Modules\Auth\Repositories\UserSessionRepository;

final class AuthenticatedUserContextFactory
{
    public static function fromSession(): UserContext
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return new UserContext(null, null);
        }

        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $companyId = isset($_SESSION['company_id']) ? (int) $_SESSION['company_id'] : null;
        $sessionToken = isset($_SESSION['session_token']) ? (string) $_SESSION['session_token'] : null;

        if ($userId === null || $companyId === null || $sessionToken === null || $sessionToken === '') {
            return new UserContext(null, null);
        }

        $sessionId = session_id();
        if (!is_string($sessionId) || $sessionId === '') {
            return new UserContext(null, null);
        }

        $ipAddress = ClientInfo::ipAddress();

        $now = new \DateTimeImmutable('now');
        $minLastActivityAt = $now->modify('-' . Config::sessionInactivityTimeoutSeconds() . ' seconds');

        $repo = new UserSessionRepository();
        try {
            $valid = $repo->findValidSessionIdentity(
                userId: $userId,
                companyId: $companyId,
                ipAddress: $ipAddress,
                sessionId: $sessionId,
                sessionToken: $sessionToken,
                now: $now,
                minLastActivityAt: $minLastActivityAt,
            );
        } catch (\Throwable) {
            // DB indisponible (ex: MySQL arrêté) : éviter un fatal.
            // On invalide la session locale et on retombe sur un contexte anonyme.
            $_SESSION = [];
            return new UserContext(null, null);
        }

        if ($valid === null) {
            // Session invalide: on nettoie côté PHP.
            $_SESSION = [];
            return new UserContext(null, null);
        }

        // Mise à jour “touch” pour éviter la révocation par inactivité.
        try {
            $repo->touchSession(
                userId: $userId,
                companyId: $companyId,
                sessionId: $sessionId,
                sessionToken: $sessionToken,
                ipAddress: $ipAddress,
            );
        } catch (\Throwable) {
            // On garde la session en mémoire pour cette requête.
            // La route protégée décidera ensuite (login si besoin).
        }

        $roles = [];
        $permissions = [];
        try {
            $rbac = new RbacService(
                rbacRepository: new RbacRepository(),
            );
            $rbacData = $rbac->getUserRolesAndPermissions($userId, $companyId);
            $roles = $rbacData['roles'];
            $permissions = $rbacData['permissions'];
        } catch (\Throwable) {
            // Si RBAC n'est pas encore initialisé, on garde un contexte “sans droits”.
        }

        $homeCompanyId = $companyId;
        $effectiveCompanyId = $companyId;
        if (!empty($_SESSION['impersonate_target_company_id'])) {
            $target = (int) $_SESSION['impersonate_target_company_id'];
            if ($target > 0 && in_array('platform.impersonate.start', $permissions, true)) {
                $effectiveCompanyId = $target;
            }
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['roles'] = $roles;
            $_SESSION['permissions'] = $permissions;
        }

        return new UserContext(
            userId: $userId,
            companyId: $effectiveCompanyId,
            roles: $roles,
            permissions: $permissions,
            homeCompanyId: $homeCompanyId,
        );
    }
}

