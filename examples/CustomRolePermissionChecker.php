<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpRbacBundle\Core\RolePermissionCheckerInterface;
use PhpRbacBundle\Entity\PermissionInterface;
use App\Entity\CustomRolePermission;

/**
 * Example of a custom permission checker that works with the CustomRolePermission entity
 * This supports scope-based permission checking for ABAC
 */
class CustomRolePermissionChecker implements RolePermissionCheckerInterface
{
    private string $permissionTableName;
    private string $roleTableName;

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        $this->permissionTableName = $this->entityManager
            ->getClassMetadata(PermissionInterface::class)
            ->getTableName();

        // Assuming CustomRole table name
        $this->roleTableName = 'custom_roles';
    }

    public function hasPermission(int $roleId, int $permissionId): bool
    {
        return $this->hasPermissionWithScope($roleId, $permissionId, null);
    }

    /**
     * Check if a role has a permission with optional scope filtering
     */
    public function hasPermissionWithScope(int $roleId, int $permissionId, ?string $scope = null): bool
    {
        $connection = $this->entityManager->getConnection();

        // Build the query with scope consideration
        $sql = "
            SELECT COUNT(*) AS result
            FROM custom_role_permissions crp
            INNER JOIN {$this->permissionTableName} AS perm_assigned ON perm_assigned.id = crp.permission_id
            INNER JOIN {$this->roleTableName} AS role_assigned ON role_assigned.id = crp.role_id
            WHERE role_assigned.tree_left BETWEEN
                (SELECT tree_left FROM {$this->roleTableName} WHERE id = :roleId)
                AND
                (SELECT tree_right FROM {$this->roleTableName} WHERE id = :roleId)
              AND perm_assigned.id IN (
                  SELECT parent.id
                  FROM {$this->permissionTableName} AS node,
                       {$this->permissionTableName} AS parent
                  WHERE node.tree_left BETWEEN parent.tree_left AND parent.tree_right
                    AND node.id = :permissionId
              )
              AND (crp.expires_at IS NULL OR crp.expires_at > NOW())
        ";

        // Add scope filtering if provided
        if ($scope !== null)
        {
            $sql .= " AND (crp.scope IS NULL OR crp.scope = :scope OR crp.scope LIKE :scopePattern)";
        }

        $query = $connection->prepare($sql);
        $query->bindValue(":roleId", $roleId);
        $query->bindValue(":permissionId", $permissionId);

        if ($scope !== null)
        {
            $query->bindValue(":scope", $scope);
            $query->bindValue(":scopePattern", $scope . ':%');
        }

        $stmt = $query->executeQuery();
        $row = $stmt->fetchAssociative();

        if ($row === false)
        {
            return false;
        }

        return $row['result'] >= 1;
    }

    public function getPermissionIdsForRole(int $roleId): array
    {
        return $this->getPermissionIdsForRoleWithScope($roleId, null);
    }

    /**
     * Get permission IDs for a role with optional scope filtering
     */
    public function getPermissionIdsForRoleWithScope(int $roleId, ?string $scope = null): array
    {
        $connection = $this->entityManager->getConnection();

        $sql = "
            SELECT DISTINCT permission.id
            FROM custom_role_permissions crp
            INNER JOIN {$this->permissionTableName} AS permission ON permission.id = crp.permission_id
            INNER JOIN {$this->roleTableName} AS role ON role.id = crp.role_id
            WHERE role.tree_left BETWEEN
                (SELECT tree_left FROM {$this->roleTableName} WHERE id = :roleId)
                AND
                (SELECT tree_right FROM {$this->roleTableName} WHERE id = :roleId)
              AND (crp.expires_at IS NULL OR crp.expires_at > NOW())
        ";

        if ($scope !== null)
        {
            $sql .= " AND (crp.scope IS NULL OR crp.scope = :scope OR crp.scope LIKE :scopePattern)";
        }

        $query = $connection->prepare($sql);
        $query->bindValue(":roleId", $roleId);

        if ($scope !== null)
        {
            $query->bindValue(":scope", $scope);
            $query->bindValue(":scopePattern", $scope . ':%');
        }

        $stmt = $query->executeQuery();

        return array_column($stmt->fetchAllAssociative(), 'id');
    }

    public function getPermissionsForRole(int $roleId): array
    {
        return $this->getPermissionsForRoleWithScope($roleId, null);
    }

    /**
     * Get permissions for a role with optional scope filtering
     */
    public function getPermissionsForRoleWithScope(int $roleId, ?string $scope = null): array
    {
        $permissionIds = $this->getPermissionIdsForRoleWithScope($roleId, $scope);

        if (empty($permissionIds))
        {
            return [];
        }

        $repository = $this->entityManager->getRepository(PermissionInterface::class);
        return $repository->findBy(['id' => $permissionIds]);
    }

    /**
     * Get all role-permission relationships for a role with scope information
     */
    public function getRolePermissionRelationships(int $roleId, ?string $scope = null): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('crp')
            ->from(CustomRolePermission::class, 'crp')
            ->join('crp.role', 'r')
            ->join('crp.permission', 'p')
            ->where('r.id = :roleId')
            ->andWhere('crp.expiresAt IS NULL OR crp.expiresAt > :now')
            ->setParameter('roleId', $roleId)
            ->setParameter('now', new \DateTime());

        if ($scope !== null)
        {
            $qb->andWhere('crp.scope IS NULL OR crp.scope = :scope OR crp.scope LIKE :scopePattern')
                ->setParameter('scope', $scope)
                ->setParameter('scopePattern', $scope . ':%');
        }

        return $qb->getQuery()->getResult();
    }
}
