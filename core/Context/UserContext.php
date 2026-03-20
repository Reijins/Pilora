<?php
declare(strict_types=1);

namespace Core\Context;

final class UserContext
{
    public function __construct(
        public readonly ?int $userId,
        public readonly ?int $companyId,
        public readonly array $roles = [],
        public readonly array $permissions = [],
        /** Société d’origine (session) ; peut différer de companyId en impersonation. */
        public readonly ?int $homeCompanyId = null,
    ) {}

    public function homeCompanyId(): ?int
    {
        return $this->homeCompanyId ?? $this->companyId;
    }

    public static function fromSession(): self
    {
        // Pour l’instant, le framework n’a pas encore l’auth DB.
        // Le contexte sera alimenté pendant la phase d’authentification.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $companyId = isset($_SESSION['company_id']) ? (int) $_SESSION['company_id'] : null;

        return new self(
            userId: $userId,
            companyId: $companyId,
            roles: isset($_SESSION['roles']) && is_array($_SESSION['roles']) ? $_SESSION['roles'] : [],
            permissions: isset($_SESSION['permissions']) && is_array($_SESSION['permissions']) ? $_SESSION['permissions'] : [],
            homeCompanyId: $companyId,
        );
    }
}

