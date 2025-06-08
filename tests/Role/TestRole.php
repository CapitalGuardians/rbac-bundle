<?php

namespace Tests\PhpRbacBundle\Role;

use Doctrine\ORM\Mapping as ORM;
use PhpRbacBundle\Entity\Role;

#[ORM\Entity]
#[ORM\Table(name: "test_role")]
class TestRole extends Role
{
    // Concrete implementation of the abstract Role class
}
