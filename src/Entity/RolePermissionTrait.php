<?php

namespace PhpRbacBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

trait RolePermissionTrait
{
    #[ORM\ManyToMany(targetEntity: PermissionInterface::class, cascade: ['persist', 'remove', 'refresh'])]
    #[ORM\JoinTable(name: "role_permissions")]
    #[ORM\JoinColumn(name: "role_id", referencedColumnName: "id", onDelete: "cascade")]
    #[ORM\InverseJoinColumn(name: "permission_id", referencedColumnName: "id", onDelete: "cascade")]
    private Collection $permissions;

    /**
     * Initialize the permissions collection
     * Call this in your constructor
     */
    public function initializePermissions(): void
    {
        $this->permissions = new ArrayCollection();
    }

    public function getPermissions(): Collection
    {
        return $this->permissions;
    }

    public function addPermission(PermissionInterface $permission): RolePermissionInterface
    {
        if (!$this->permissions->contains($permission))
        {
            $this->permissions->add($permission);
        }

        return $this;
    }

    public function removePermission(PermissionInterface $permission): RolePermissionInterface
    {
        $this->permissions->removeElement($permission);

        return $this;
    }

    public function setPermissions(?Collection $permissions): RolePermissionInterface
    {
        $this->permissions = $permissions ?? new ArrayCollection();

        return $this;
    }
}
