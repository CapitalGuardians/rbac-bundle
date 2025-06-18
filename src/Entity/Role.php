<?php

namespace PhpRbacBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class Role extends Node implements RoleInterface
{
    #[ORM\ManyToOne(targetEntity: RoleInterface::class)]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: "cascade")]
    protected ?RoleInterface $parent = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParent(): ?RoleInterface
    {
        return $this->parent;
    }

    public function setParent(?RoleInterface $parent): RoleInterface
    {
        $this->parent = $parent;

        return $this;
    }
}
