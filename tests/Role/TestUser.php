<?php

namespace Tests\PhpRbacBundle\Role;

use Doctrine\ORM\Mapping as ORM;
use PhpRbacBundle\Entity\UserRoleInterface;
use PhpRbacBundle\Entity\UserRoleTrait;

#[ORM\Entity]
#[ORM\Table(name: "test_user")]
class TestUser implements UserRoleInterface
{
    use UserRoleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255)]
    private string $username;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }
}
