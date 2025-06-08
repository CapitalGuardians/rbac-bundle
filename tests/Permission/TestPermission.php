<?php

namespace Tests\PhpRbacBundle\Permission;

use Doctrine\ORM\Mapping as ORM;
use PhpRbacBundle\Entity\Permission;

#[ORM\Entity]
#[ORM\Table(name: "test_permission")]
class TestPermission extends Permission
{
    // Concrete implementation of the abstract Permission class
}
