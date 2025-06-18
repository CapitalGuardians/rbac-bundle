<?php

namespace PhpRbacBundle\Entity;

interface RoleInterface extends NodeInterface
{
    public function getParent(): ?RoleInterface;

    public function setParent(?RoleInterface $parent): RoleInterface;
}
