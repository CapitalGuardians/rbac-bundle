<?php

namespace PhpRbacBundle\Core;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use PhpRbacBundle\Entity\PermissionInterface;

class DefaultRolePermissionChecker implements RolePermissionCheckerInterface
{
    private string $permissionTableName;
    private string $roleTableName;

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        $this->permissionTableName = $this->entityManager
            ->getClassMetadata(PermissionInterface::class)
            ->getTableName();

        // Get the role table name from any role entity metadata
        $allMetadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        foreach ($allMetadata as $metadata)
        {
            if ($metadata->getReflectionClass()->implementsInterface('PhpRbacBundle\Entity\RoleInterface'))
            {
                $this->roleTableName = $metadata->getTableName();
                break;
            }
        }
    }

    public function hasPermission(int $roleId, int $permissionId): bool
    {
        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        if ($platform instanceof SqlitePlatform)
        {
            // SQLite-compatible version
            $sql = "
                SELECT COUNT(*) AS result
                FROM role_permissions rp
                INNER JOIN {$this->permissionTableName} AS perm_assigned ON perm_assigned.id = rp.permission_id
                INNER JOIN {$this->roleTableName} AS role_assigned ON role_assigned.id = rp.role_id
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
            ";
        }
        else
        {
            // Original query for MySQL/PostgreSQL
            $sql = "
                SELECT
                    COUNT(*) AS result
                    FROM role_permissions
                    INNER JOIN {$this->permissionTableName} AS permission ON permission.id = role_permissions.permission_id
                    INNER JOIN {$this->roleTableName} AS role ON role.id = role_permissions.role_id
                WHERE
                    role.tree_left BETWEEN
                        (SELECT tree_left FROM {$this->roleTableName} WHERE ID = :roleId)
                        AND
                        (SELECT tree_right FROM {$this->roleTableName} WHERE ID = :roleId)
                    AND
                        permission.id IN (
                            SELECT
                                parent.id
                            FROM
                                {$this->permissionTableName} AS node,
                                {$this->permissionTableName} AS parent
                            WHERE
                                node.tree_left BETWEEN parent.tree_left AND parent.tree_right
                                AND node.ID= :permissionId
                            ORDER BY parent.tree_left
                        )
            ";
        }

        $query = $connection->prepare($sql);
        $query->bindValue(":roleId", $roleId);
        $query->bindValue(":permissionId", $permissionId);
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
        $connection = $this->entityManager->getConnection();

        $sql = "
            SELECT DISTINCT permission.id
            FROM role_permissions
            INNER JOIN {$this->permissionTableName} AS permission ON permission.id = role_permissions.permission_id
            INNER JOIN {$this->roleTableName} AS role ON role.id = role_permissions.role_id
            WHERE role.tree_left BETWEEN
                (SELECT tree_left FROM {$this->roleTableName} WHERE id = :roleId)
                AND
                (SELECT tree_right FROM {$this->roleTableName} WHERE id = :roleId)
        ";

        $query = $connection->prepare($sql);
        $query->bindValue(":roleId", $roleId);
        $stmt = $query->executeQuery();

        return array_column($stmt->fetchAllAssociative(), 'id');
    }

    public function getPermissionsForRole(int $roleId): array
    {
        $permissionIds = $this->getPermissionIdsForRole($roleId);

        if (empty($permissionIds))
        {
            return [];
        }

        $repository = $this->entityManager->getRepository(PermissionInterface::class);
        return $repository->findBy(['id' => $permissionIds]);
    }
}
