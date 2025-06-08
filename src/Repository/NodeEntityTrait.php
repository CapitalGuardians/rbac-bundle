<?php

namespace PhpRbacBundle\Repository;

use PhpRbacBundle\Entity\Node;
use PhpRbacBundle\Exception\RbacException;
use PhpRbacBundle\Core\Manager\NodeManagerInterface;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;

trait NodeEntityTrait
{
    public function deleteNode(int $nodeId): bool
    {
        if ($nodeId == NodeManagerInterface::ROOT_ID)
        {
            throw new RbacException("The Root Node cannot be deleted");
        }

        $entityManager = $this->getEntityManager();

        $info = $this->getById($nodeId);

        $dql = "DELETE {$this->getClassName()} node WHERE node.left = :left";
        $query = $entityManager->createQuery($dql);
        $query->setParameter(":left", $info->getLeft());
        $query->execute();

        $dql = "UPDATE {$this->getClassName()} node SET node.right = node.right - 1, node.left = node.left - 1 WHERE node.left BETWEEN :left AND :right";
        $query = $entityManager->createQuery($dql);
        $query->setParameter(":left", $info->getLeft());
        $query->setParameter(":right", $info->getRight());
        $query->execute();

        $dql = "UPDATE {$this->getClassName()} node SET node.right = node.right - 2 WHERE node.right > :right";
        $query = $entityManager->createQuery($dql);
        $query->setParameter(":right", $info->getRight());
        $query->execute();

        $dql = "UPDATE {$this->getClassName()} node SET node.left = node.left - 2 WHERE node.left > :right";
        $query = $entityManager->createQuery($dql);
        $query->setParameter(":right", $info->getRight());
        $query->execute();

        return true;
    }

    public function deleteSubtree(int $nodeId): bool
    {
        if ($nodeId == NodeManagerInterface::ROOT_ID)
        {
            throw new RbacException("The Root Node cannot be deleted");
        }

        $entityManager = $this->getEntityManager();

        $info = $this->getById($nodeId);
        $width = $info->getRight() - $info->getLeft() + 1;

        $dql = "DELETE {$this->getClassName()} node WHERE node.left BETWEEN :left AND :right";
        $query = $entityManager->createQuery($dql);
        $query->setParameter(":left", $info->getLeft());
        $query->setParameter(":right", $info->getRight());
        $query->execute();

        $dql = "UPDATE {$this->getClassName()} node SET node.right = node.right - :widht WHERE node.right > :right";
        $query = $entityManager->createQuery($dql);
        $query->setParameter(":width", $width);
        $query->setParameter(":right", $info->getRight());
        $query->execute();

        $dql = "UPDATE {$this->getClassName()} node SET node.left = node.left - :widht WHERE node.left > :right";
        $query = $entityManager->createQuery($dql);
        $query->setParameter(":width", $width);
        $query->setParameter(":right", $info->getRight());
        $query->execute();

        return true;
    }

    public function pathId(string $path, string $classException): mixed
    {
        $pathCmpl = "root" . strtolower($path);

        $tableName = $this->getClassMetadata()
            ->getTableName();
        $parts = explode("/", $pathCmpl);
        $platform = $this->getEntityManager()->getConnection()->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform)
        {
            $sql = "
            SELECT
                node.id,
                string_agg(parent.code, '/' ORDER BY parent.tree_left) as path
            FROM
                {$tableName} as parent
            INNER JOIN
                {$tableName} as node ON node.tree_left BETWEEN parent.tree_left AND parent.tree_right
            WHERE
                node.code = :code
            GROUP BY
                node.id
            HAVING
                string_agg(parent.code, '/' ORDER BY parent.tree_left) = :path
                ";

            $pdo = $this->getEntityManager()->getConnection();
            $query = $pdo->prepare($sql);
            $finalPart = end($parts);
            $query->bindValue(":code", strtolower($finalPart));
            $query->bindValue(":path", $pathCmpl);
            $result = $query->executeQuery();

            if ($result->rowCount() == 0)
            {
                throw new $classException($path);
            }

            $row = $result->fetchAssociative();
            return $row['id'];
        }
        elseif ($platform instanceof SqlitePlatform)
        {
            // For SQLite, we'll use a simpler approach since GROUP_CONCAT doesn't preserve order reliably
            // First, find all nodes with the matching code
            $sql = "
            SELECT DISTINCT node.id
            FROM {$tableName} as node
            WHERE node.code = :code
            ";

            $pdo = $this->getEntityManager()->getConnection();
            $query = $pdo->prepare($sql);
            $finalPart = end($parts);
            $query->bindValue(":code", strtolower($finalPart));
            $result = $query->executeQuery();

            // Check each candidate node to see if its path matches
            while ($row = $result->fetchAssociative())
            {
                $nodeId = $row['id'];

                // Build the path for this node
                $pathSql = "
                SELECT parent.code
                FROM {$tableName} as parent
                INNER JOIN {$tableName} as node ON node.tree_left BETWEEN parent.tree_left AND parent.tree_right
                WHERE node.id = :nodeId
                ORDER BY parent.tree_left
                ";

                $pathQuery = $pdo->prepare($pathSql);
                $pathQuery->bindValue(":nodeId", $nodeId);
                $pathResult = $pathQuery->executeQuery();

                $pathParts = [];
                while ($pathRow = $pathResult->fetchAssociative())
                {
                    $pathParts[] = $pathRow['code'];
                }

                $fullPath = implode('/', $pathParts);
                if ($fullPath === $pathCmpl)
                {
                    return $nodeId;
                }
            }

            // If we get here, no matching path was found
            throw new $classException($path);
        }
        else
        {
            // MySQL
            $sql = "
            SELECT
                node.id,
                GROUP_CONCAT(parent.code ORDER BY parent.tree_left SEPARATOR '/') as path
            FROM
                {$tableName} as parent
            INNER JOIN
                {$tableName} as node ON node.tree_left BETWEEN parent.tree_left AND parent.tree_right
            WHERE
                node.code = :code
            GROUP BY
                node.id
            HAVING
                path = :path
        ";

            $pdo = $this->getEntityManager()->getConnection();
            $query = $pdo->prepare($sql);
            $finalPart = end($parts);
            $query->bindValue(":code", strtolower($finalPart));
            $query->bindValue(":path", $pathCmpl);
            $result = $query->executeQuery();

            if ($result->rowCount() == 0)
            {
                throw new $classException($path);
            }

            $row = $result->fetchAssociative();
            return $row['id'];
        }
    }

    public function reset()
    {
        $tableName = $this->getClassMetadata()
            ->getTableName();
        $platform = $this->getEntityManager()->getConnection()->getDatabasePlatform();
        $connection = $this->getEntityManager()->getConnection();

        if ($platform instanceof SqlitePlatform)
        {
            // SQLite doesn't support AUTO_INCREMENT reset directly, but sequence resets when table is empty
            $connection->executeQuery("DELETE FROM {$tableName} WHERE id > 1");
            $connection->executeQuery("UPDATE {$tableName} SET tree_left = 0, tree_right = 1 WHERE id = 1");
            // For SQLite, we don't need to reset AUTO_INCREMENT as it handles it automatically
        }
        else
        {
            // MySQL/PostgreSQL
            $connection->executeQuery("DELETE FROM {$tableName} WHERE id > 1");
            $connection->executeQuery("UPDATE {$tableName} SET tree_left = 0, tree_right = 1 WHERE id = 1");
            $connection->executeQuery("ALTER TABLE {$tableName} AUTO_INCREMENT = 2");
        }
    }

    public function updateForAdd(int $parentId, string $nodeClass, string $code, string $description): Node
    {
        $entityManager = $this->getEntityManager();

        $parent = $this->getById($parentId);

        $dql = "UPDATE {$this->getClassName()} node SET node.right = node.right + 2 WHERE node.right >= :right";
        $query = $entityManager->createQuery($dql);
        $query->setParameter(":right", $parent->getRight());
        $query->execute();

        $dql = "UPDATE {$this->getClassName()} node SET node.left = node.left + 2 WHERE node.left >= :right";
        $query = $entityManager->createQuery($dql);
        $query->setParameter(":right", $parent->getRight());
        $query->execute();

        $node = new $nodeClass();
        $node->setCode($code)
            ->setParent($parent)
            ->setDescription($description)
            ->setLeft($parent->getRight())
            ->setRight($parent->getRight() + 1);

        $this->add($node, true);

        $entityManager->refresh($parent);

        return $node;
    }

    private function getPathFunc(int $nodeId, string $rbacExceptionClass): array
    {
        $dql = "
            SELECT
                parent
            FROM
                {$this->getClassName()} parent
            JOIN
                {$this->getClassName()} node WITH node.left BETWEEN parent.left AND parent.right
            WHERE
                node.id = :nodeId
            ORDER BY
                parent.left
        ";

        $query = $this->getEntityManager()
            ->createQuery($dql);
        $query->setParameter(':nodeId', $nodeId);
        $result = $query->getResult();

        if (empty($result))
        {
            throw new $rbacExceptionClass();
        }

        return $result;
    }
}
