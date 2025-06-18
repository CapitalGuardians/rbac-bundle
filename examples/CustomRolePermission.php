<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use PhpRbacBundle\Entity\PermissionInterface;

/**
 * Example of a custom join entity that includes scope for ABAC
 * This replaces the simple role_permissions pivot table
 */
#[ORM\Entity]
#[ORM\Table(name: 'custom_role_permissions')]
class CustomRolePermission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CustomRole::class, inversedBy: 'rolePermissions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CustomRole $role = null;

    #[ORM\ManyToOne(targetEntity: PermissionInterface::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PermissionInterface $permission = null;

    /**
     * The scope field enables ABAC (Attribute-Based Access Control)
     * Examples: 'organization:123', 'department:sales', 'project:456', etc.
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $scope = null;

    /**
     * Optional: Additional metadata for the permission relationship
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    /**
     * Optional: Expiration date for time-limited permissions
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $expiresAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRole(): ?CustomRole
    {
        return $this->role;
    }

    public function setRole(?CustomRole $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getPermission(): ?PermissionInterface
    {
        return $this->permission;
    }

    public function setPermission(?PermissionInterface $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function setScope(?string $scope): self
    {
        $this->scope = $scope;

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    /**
     * Check if this permission relationship is currently valid
     */
    public function isValid(): bool
    {
        if ($this->expiresAt === null)
        {
            return true;
        }

        return $this->expiresAt > new \DateTime();
    }

    /**
     * Check if this permission matches a given scope pattern
     */
    public function matchesScope(?string $scopePattern): bool
    {
        if ($scopePattern === null)
        {
            return true;
        }

        if ($this->scope === null)
        {
            return false;
        }

        // Simple pattern matching - you can implement more complex logic
        return $this->scope === $scopePattern || str_starts_with($this->scope, $scopePattern . ':');
    }
}
