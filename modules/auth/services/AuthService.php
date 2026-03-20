<?php
declare(strict_types=1);

namespace Modules\Auth\Services;

use Core\Config;
use Core\Http\ClientInfo;
use Modules\Auth\Repositories\UserRepository;
use Modules\Auth\Repositories\UserSessionRepository;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}

    public function login(string $email, string $password): bool
    {
        $user = $this->userRepository->findActiveByEmail($email);
        if ($user === null) {
            return false;
        }

        if (!password_verify($password, $user['passwordHash'])) {
            return false;
        }

        // Durcissement contre session fixation.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $ipAddress = ClientInfo::ipAddress();
        $userAgent = ClientInfo::userAgent();
        $userId = (int) $user['id'];
        $companyId = (int) $user['companyId'];

        $now = new \DateTimeImmutable('now');
        $expiresAt = $now->modify('+' . Config::sessionLifetimeSeconds() . ' seconds');

        $sessionToken = bin2hex(random_bytes(32));
        $sessionId = session_id();
        if (!is_string($sessionId) || $sessionId === '') {
            return false;
        }

        $sessionRepo = new UserSessionRepository();

        // Règle critique: un utilisateur ne peut pas avoir plusieurs sessions actives
        // simultanées depuis la même IP => on invalide les sessions existantes.
        $sessionRepo->revokeActiveSessionsByUserIp(
            userId: $userId,
            companyId: $companyId,
            ipAddress: $ipAddress,
        );

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['company_id'] = $companyId;
        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['roles'] = [];
        $_SESSION['permissions'] = [];

        $_SESSION['last_activity_at'] = time();

        try {
            $sessionRepo->createSession([
                'userId' => $userId,
                'companyId' => $companyId,
                'ipAddress' => $ipAddress,
                'userAgent' => $userAgent,
                'sessionId' => $sessionId,
                'sessionToken' => $sessionToken,
                'lastActivityAt' => $now->format('Y-m-d H:i:s'),
                'expiresAt' => $expiresAt->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // En dev: si la BDD n'est pas encore initialisée, on échoue proprement.
            return false;
        }

        return true;
    }

    public function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            return;
        }

        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $companyId = isset($_SESSION['company_id']) ? (int) $_SESSION['company_id'] : null;
        $sessionToken = isset($_SESSION['session_token']) ? (string) $_SESSION['session_token'] : null;
        $sessionId = session_id();
        $ipAddress = ClientInfo::ipAddress();

        if ($userId !== null && $companyId !== null && $sessionToken !== null && is_string($sessionId) && $sessionId !== '') {
            $repo = new UserSessionRepository();
            $repo->revokeCurrentSession(
                userId: $userId,
                companyId: $companyId,
                sessionId: $sessionId,
                sessionToken: $sessionToken,
                ipAddress: $ipAddress,
            );
        }

        unset($_SESSION['impersonate_target_company_id']);
        $_SESSION = [];
        session_regenerate_id(true);
        session_destroy();
    }
}

