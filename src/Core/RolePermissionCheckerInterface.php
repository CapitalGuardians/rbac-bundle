<?php

namespace PhpRbacBundle\Core;

interface RolePermissionCheckerInterface
{
    /**
     * Check if a role has a specific permission
     */
    public function hasPermission(int $roleId, int $permissionId): bool;

    /**
     * Get all permission IDs for a role
     */
    public function getPermissionIdsForRole(int $roleId): array;

    /**
     * Get all permissions for a role
     */
    public function getPermissionsForRole(int $roleId): array;
}
