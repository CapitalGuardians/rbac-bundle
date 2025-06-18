<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use PhpRbacBundle\Entity\Role;

/**
 * Example of a custom role implementation with OneToMany relationship
 * to a custom join entity that includes scope for ABAC
 */
#[ORM\Entity]
#[ORM\Table(name: 'custom_roles')]
class CustomRole extends Role
{
    #[ORM\OneToMany(targetEntity: CustomRolePermission::class, mappedBy: 'role', cascade: ['persist', 'remove'])]
    private Collection $rolePermissions;

    public function __construct()
    {
        $this->rolePermissions = new ArrayCollection();
    }

    /**
     * Get all role-permission relationships
     */
    public function getRolePermissions(): Collection
    {
        return $this->rolePermissions;
    }

    /**
     * Add a role-permission relationship with scope
     */
    public function addRolePermission(CustomRolePermission $rolePermission): self
    {
        if (!$this->rolePermissions->contains($rolePermission))
        {
            $this->rolePermissions->add($rolePermission);
            $rolePermission->setRole($this);
        }

        return $this;
    }

    /**
     * Remove a role-permission relationship
     */
    public function removeRolePermission(CustomRolePermission $rolePermission): self
    {
        if ($this->rolePermissions->removeElement($rolePermission))
        {
            if ($rolePermission->getRole() === $this)
            {
                $rolePermission->setRole(null);
            }
        }

        return $this;
    }

    /**
     * Check if role has permission with optional scope
     */
    public function hasPermissionWithScope(int $permissionId, ?string $scope = null): bool
    {
        foreach ($this->rolePermissions as $rolePermission)
        {
            if ($rolePermission->getPermission()->getId() === $permissionId)
            {
                if ($scope === null || $rolePermission->getScope() === $scope)
                {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get permissions for a specific scope
     */
    public function getPermissionsForScope(?string $scope = null): array
    {
        $permissions = [];
        foreach ($this->rolePermissions as $rolePermission)
        {
            if ($scope === null || $rolePermission->getScope() === $scope)
            {
                $permissions[] = $rolePermission->getPermission();
            }
        }

        return $permissions;
    }
}
