<?php

namespace Tests\PhpRbacBundle;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use PhpRbacBundle\Core\Rbac;
use PhpRbacBundle\Core\Manager\RoleManager;
use PhpRbacBundle\Core\Manager\PermissionManager;
use PhpRbacBundle\Core\Manager\NodeManagerInterface;
use PhpRbacBundle\Repository\PermissionRepository;
use PhpRbacBundle\Repository\RoleRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\PhpRbacBundle\Fixtures\TestKernel;

class UserRoleTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }
    private PermissionRepository $permissionRepository;
    private RoleRepository $roleRepository;
    private RoleManager $roleManager;
    private PermissionManager $permissionManager;
    private Rbac $rbac;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->permissionRepository = $container->get(PermissionRepository::class);
        $this->roleRepository = $container->get(RoleRepository::class);
        $this->roleManager = $container->get(RoleManager::class);
        $this->permissionManager = $container->get(PermissionManager::class);
        $this->rbac = $container->get(Rbac::class);

        // Initialize tables for each test
        $this->permissionRepository->initTable();
        $this->roleRepository->initTable();
    }

    public function testHasRoleWithRoleObject(): void
    {
        $role = $this->roleManager->add("editor", "Editor", NodeManagerInterface::ROOT_ID);

        // Assign user 1 to the role
        $this->assignUserToRole(1, $role->getId());

        // Test with role object
        $this->assertTrue($this->rbac->hasRole($role, 1));
        $this->assertFalse($this->rbac->hasRole($role, 999));
    }

    public function testHasRoleWithRoleId(): void
    {
        $role = $this->roleManager->add("editor", "Editor", NodeManagerInterface::ROOT_ID);

        // Assign user 1 to the role
        $this->assignUserToRole(1, $role->getId());

        // Test with role ID
        $this->assertTrue($this->rbac->hasRole($role->getId(), 1));
        $this->assertFalse($this->rbac->hasRole($role->getId(), 999));
    }

    public function testHasRoleWithRolePath(): void
    {
        $role = $this->roleManager->add("editor", "Editor", NodeManagerInterface::ROOT_ID);

        // Assign user 1 to the editor role
        $this->assignUserToRole(1, $role->getId());

        // Test with role path
        $this->assertTrue($this->rbac->hasRole("/editor", 1));
        $this->assertFalse($this->rbac->hasRole("/editor", 999));
        $this->assertFalse($this->rbac->hasRole("/", 1));
    }

    public function testHasRoleWithAdminRole(): void
    {
        $role = $this->roleManager->add("editor", "Editor", NodeManagerInterface::ROOT_ID);
        $roleAdmin = $this->roleManager->getNode(NodeManagerInterface::ROOT_ID);

        // Assign user 1 to the root/admin role
        $this->assignUserToRole(1, NodeManagerInterface::ROOT_ID);

        // Admin role should have access to all sub-roles
        $this->assertTrue($this->rbac->hasRole($role, 1));
        $this->assertTrue($this->rbac->hasRole($role->getId(), 1));
        $this->assertTrue($this->rbac->hasRole("/editor", 1));
        $this->assertTrue($this->rbac->hasRole("/", 1));
    }

    public function testEditorPermissions(): void
    {
        // Create permission structure
        $this->permissionManager->addPath("/notepad/todolist/read", [
            "notepad" => "Notepad",
            "todolist" => "Todo list",
            "read" => "Read Access"
        ]);
        $this->permissionManager->addPath("/notepad/todolist/write", [
            "notepad" => "Notepad",
            "todolist" => "Todo list",
            "write" => "Write Access"
        ]);

        // Create role structure
        $this->roleManager->addPath("/editor/reviewer", [
            "editor" => "Editor",
            "reviewer" => "Reviewer"
        ]);

        $editorId = $this->roleManager->getPathId("/editor");
        $editor = $this->roleManager->getNode($editorId);
        $reviewerId = $this->roleManager->getPathId("/editor/reviewer");
        $reviewer = $this->roleManager->getNode($reviewerId);

        // Assign permissions
        $this->roleManager->assignPermission($editor, "/notepad");
        $this->roleManager->assignPermission($reviewer, "/notepad/todolist/read");
        $this->roleManager->assignPermission($reviewer, "/notepad/todolist/write");

        // Assign user 1 to the editor role
        $this->assignUserToRole(1, $editorId);

        // Test editor permissions (should have access to all notepad permissions)
        $this->assertTrue($this->rbac->hasPermission("/notepad", 1));
        $this->assertTrue($this->rbac->hasPermission("/notepad/todolist", 1));
        $this->assertTrue($this->rbac->hasPermission("/notepad/todolist/read", 1));
        $this->assertTrue($this->rbac->hasPermission("/notepad/todolist/write", 1));
        $this->assertFalse($this->rbac->hasPermission("/", 1));
    }

    public function testReviewerPermissions(): void
    {
        // Create permission structure
        $this->permissionManager->addPath("/notepad/todolist/read", [
            "notepad" => "Notepad",
            "todolist" => "Todo list",
            "read" => "Read Access"
        ]);
        $this->permissionManager->addPath("/notepad/todolist/write", [
            "notepad" => "Notepad",
            "todolist" => "Todo list",
            "write" => "Write Access"
        ]);

        // Create role structure
        $this->roleManager->addPath("/editor/reviewer", [
            "editor" => "Editor",
            "reviewer" => "Reviewer"
        ]);

        $editorId = $this->roleManager->getPathId("/editor");
        $editor = $this->roleManager->getNode($editorId);
        $reviewerId = $this->roleManager->getPathId("/editor/reviewer");
        $reviewer = $this->roleManager->getNode($reviewerId);

        // Assign permissions
        $this->roleManager->assignPermission($editor, "/notepad");
        $this->roleManager->assignPermission($reviewer, "/notepad/todolist/read");
        $this->roleManager->assignPermission($reviewer, "/notepad/todolist/write");

        // Assign user 999 to the reviewer role
        $this->assignUserToRole(999, $reviewerId);

        // Test reviewer permissions (limited access)
        $this->assertFalse($this->rbac->hasPermission("/notepad", 999));
        $this->assertFalse($this->rbac->hasPermission("/notepad/todolist", 999));
        $this->assertTrue($this->rbac->hasPermission("/notepad/todolist/read", 999));
        $this->assertTrue($this->rbac->hasPermission("/notepad/todolist/write", 999));
        $this->assertFalse($this->rbac->hasPermission("/", 999));
    }

    public function testPermissionInheritance(): void
    {
        // Create nested permission structure
        $this->permissionManager->addPath("/system/users/create", [
            "system" => "System",
            "users" => "User Management",
            "create" => "Create Users"
        ]);
        $this->permissionManager->addPath("/system/users/edit", [
            "system" => "System",
            "users" => "User Management",
            "edit" => "Edit Users"
        ]);

        // Create role and assign parent permission
        $adminRole = $this->roleManager->add("admin", "Administrator", NodeManagerInterface::ROOT_ID);
        $this->roleManager->assignPermission($adminRole, "/system");

        // Assign user 1 to the admin role
        $this->assignUserToRole(1, $adminRole->getId());

        // Admin should inherit all sub-permissions
        $this->assertTrue($this->rbac->hasPermission("/system", 1));
        $this->assertTrue($this->rbac->hasPermission("/system/users", 1));
        $this->assertTrue($this->rbac->hasPermission("/system/users/create", 1));
        $this->assertTrue($this->rbac->hasPermission("/system/users/edit", 1));
    }

    private function assignUserToRole(int $userId, int $roleId): void
    {
        $connection = $this->roleRepository->getEntityManager()->getConnection();
        $platform = $connection->getDatabasePlatform();

        if ($platform instanceof SqlitePlatform)
        {
            // Temporarily disable foreign key constraints for SQLite
            $connection->executeQuery("PRAGMA foreign_keys = OFF");
            $sql = "INSERT INTO user_roles (user_id, role_id) VALUES (:userId, :roleId)";
            $connection->executeQuery($sql, ['userId' => $userId, 'roleId' => $roleId]);
            $connection->executeQuery("PRAGMA foreign_keys = ON");
        }
        else
        {
            $sql = "INSERT INTO user_roles (user_id, role_id) VALUES (:userId, :roleId)";
            $connection->executeQuery($sql, ['userId' => $userId, 'roleId' => $roleId]);
        }
    }

    protected function tearDown(): void
    {
        // Clean up database tables for next test
        if (isset($this->permissionRepository))
        {
            $this->permissionRepository->initTable();
        }
        if (isset($this->roleRepository))
        {
            $this->roleRepository->initTable();
        }

        parent::tearDown();
    }
}
