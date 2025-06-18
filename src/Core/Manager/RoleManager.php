<?php

namespace PhpRbacBundle\Core\Manager;

use PhpRbacBundle\Entity\RoleInterface;
use PhpRbacBundle\Entity\RolePermissionInterface;
use PhpRbacBundle\Repository\RoleRepository;
use PhpRbacBundle\Core\RolePermissionCheckerInterface;

/**
 * @property RoleRepository $repository
 */
class RoleManager extends NodeManager implements RoleManagerInterface
{
    public function __construct(
        private readonly PermissionManager $permissionManager,
        RoleRepository $roleRepository,
        private readonly ?RolePermissionCheckerInterface $permissionChecker = null
    ) {
        parent::__construct($roleRepository);
    }

    public function remove(RoleInterface $role): bool
    {
        return $this->repository->deleteNode($role->getId());
    }

    public function removeRecursively(RoleInterface $role): bool
    {
        return $this->repository->deleteSubtree($role->getId());
    }

    public function assignPermission(RoleInterface $role, string $permission)
    {
        if (!$role instanceof RolePermissionInterface)
        {
            throw new \InvalidArgumentException('Role must implement RolePermissionInterface to assign permissions');
        }

        $nodeId = $this->permissionManager->getPathId($permission);
        $node = $this->permissionManager->getNode($nodeId);
        $role->addPermission($node);
        $this->repository->add($role, true);
    }

    public function unassignPermission(RoleInterface $role, string $permission)
    {
        if (!$role instanceof RolePermissionInterface)
        {
            throw new \InvalidArgumentException('Role must implement RolePermissionInterface to unassign permissions');
        }

        $nodeId = $this->permissionManager->getPathId($permission);
        $node = $this->permissionManager->getNode($nodeId);
        $role->removePermission($node);
        $this->repository->add($role, true);
    }

    public function unassignPermissions(RoleInterface $role): bool
    {
        if (!$role instanceof RolePermissionInterface)
        {
            throw new \InvalidArgumentException('Role must implement RolePermissionInterface to unassign permissions');
        }

        $role->setPermissions(null);
        $this->repository->add($role, true);

        return empty($role->getPermissions());
    }

    public function hasPermission(int $roleId, int $permissionId): bool
    {
        if ($this->permissionChecker)
        {
            return $this->permissionChecker->hasPermission($roleId, $permissionId);
        }

        // Fallback to deprecated repository method
        return $this->repository->hasPermission($roleId, $permissionId);
    }

    public function hasRole(int $roleId, mixed $userId): bool
    {
        return $this->repository->hasRole($roleId, $userId);
    }
}
