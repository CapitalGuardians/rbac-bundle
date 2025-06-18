<?php

namespace PhpRbacBundle\Entity;

use Doctrine\Common\Collections\Collection;

interface RolePermissionInterface
{
    public function getPermissions(): Collection;

    public function addPermission(PermissionInterface $permission): RolePermissionInterface;

    public function removePermission(PermissionInterface $permission): RolePermissionInterface;

    public function setPermissions(?Collection $permissions): RolePermissionInterface;
}
