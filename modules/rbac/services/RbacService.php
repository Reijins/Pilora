<?php
declare(strict_types=1);

namespace Modules\Rbac\Services;

use Modules\Rbac\Repositories\RbacRepository;

final class RbacService
{
    public function __construct(
        private readonly RbacRepository $rbacRepository,
    ) {}

    public function getUserRolesAndPermissions(int $userId, int $companyId): array
    {
        $roles = $this->rbacRepository->getUserRoles($userId, $companyId);
        $permissions = $this->rbacRepository->getUserPermissions($userId, $companyId);

        return [
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }
}

