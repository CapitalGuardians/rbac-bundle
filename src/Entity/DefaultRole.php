<?php

namespace PhpRbacBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Default Role implementation that provides backward compatibility
 * Uses the RolePermissionTrait to maintain the original ManyToMany relationship
 */
#[ORM\MappedSuperclass]
abstract class DefaultRole extends Role implements RolePermissionInterface
{
    use RolePermissionTrait;

    public function __construct()
    {
        $this->initializePermissions();
    }
}
