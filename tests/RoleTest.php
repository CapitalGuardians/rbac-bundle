<?php

namespace Tests\PhpRbacBundle;

use Exception;
use PhpRbacBundle\Core\Manager\RoleManager;
use PhpRbacBundle\Core\Manager\PermissionManager;
use PhpRbacBundle\Core\Manager\NodeManagerInterface;
use PhpRbacBundle\Repository\PermissionRepository;
use PhpRbacBundle\Repository\RoleRepository;
use PhpRbacBundle\Exception\RbacRoleNotFoundException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\PhpRbacBundle\Fixtures\TestKernel;

class RoleTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }
    private PermissionRepository $permissionRepository;
    private RoleRepository $roleRepository;
    private RoleManager $roleManager;
    private PermissionManager $permissionManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->permissionRepository = $container->get(PermissionRepository::class);
        $this->roleRepository = $container->get(RoleRepository::class);
        $this->roleManager = $container->get(RoleManager::class);
        $this->permissionManager = $container->get(PermissionManager::class);

        // Initialize tables for each test
        $this->permissionRepository->initTable();
        $this->roleRepository->initTable();
    }

    public function testSearchRole(): void
    {
        try
        {
            $roleId = $this->roleManager->getPathId("/");
            $this->assertEquals(NodeManagerInterface::ROOT_ID, $roleId);
        }
        catch (Exception $e)
        {
            $this->fail($e->getMessage());
        }
    }

    public function testAddRole(): void
    {
        $role = $this->roleManager->add("Editor", "Editor", NodeManagerInterface::ROOT_ID);
        $this->assertGreaterThan(0, $role->getId());
        $this->assertSame("editor", $role->getCode());
        $this->assertSame("Editor", $role->getDescription());

        $path = $this->roleManager->getPath($role->getId());
        $this->assertSame("/editor", $path);

        $roleId = $this->roleManager->getPathId("/editor");

        $this->assertSame($role->getId(), $roleId);
    }

    /**
     * @depends testAddRole
     */
    public function testAddDoubleRole(): void
    {
        $role1 = $this->roleManager->add("Editor", "Editor", NodeManagerInterface::ROOT_ID);
        $role2 = $this->roleManager->add("Editor", "Editor", NodeManagerInterface::ROOT_ID);

        $this->assertSame($role1->getId(), $role2->getId());
    }

    /**
     * @depends testAddRole
     */
    public function testAddSubRole(): void
    {
        $role = $this->roleManager->add("Editor", "Editor", NodeManagerInterface::ROOT_ID);
        $subRole = $this->roleManager->add("reviewer", "Reviewer", $role->getId());

        $this->assertGreaterThan($role->getId(), $subRole->getId(), "Error ID");
        $this->assertGreaterThan($role->getLeft(), $subRole->getLeft(), "Error left");
        $this->assertLessThan($role->getRight(), $subRole->getRight(), "Error Right {$role->getRight()} {$subRole->getRight()}");
    }

    /**
     * @depends testAddSubRole
     */
    public function testAddDoubleSubRole(): void
    {
        $role = $this->roleManager->add("Editor", "Editor", NodeManagerInterface::ROOT_ID);
        $subRole1 = $this->roleManager->add("reviewer", "Reviewer", $role->getId());
        $subRole2 = $this->roleManager->add("reviewer", "Reviewer", $role->getId());
        $this->assertSame($subRole1->getId(), $subRole2->getId());
    }

    /**
     * @depends testAddRole
     */
    public function testAddPath(): void
    {
        $role = $this->roleManager->addPath("/editor/reviewer", ["editor" => "Editor", "reviewer" => "Reviewer"]);
        $this->assertGreaterThan(0, $role->getId());
        $this->assertSame("reviewer", $role->getCode());
        $this->assertSame("Reviewer", $role->getDescription());

        $role = $this->roleManager->getNode($this->roleManager->getPathId("/editor"));
        $subRole = $this->roleManager->getNode($this->roleManager->getPathId("/editor/reviewer"));

        $this->assertGreaterThan($role->getId(), $subRole->getId(), "Error ID");
        $this->assertGreaterThan($role->getLeft(), $subRole->getLeft(), "Error left");
        $this->assertLessThan($role->getRight(), $subRole->getRight(), "Error Right {$role->getRight()} {$subRole->getRight()}");
    }

    public function testPath(): void
    {
        $this->roleManager->addPath("/editor/reviewer", ["editor" => "Editor", "reviewer" => "Reviewer"]);
        $id = $this->roleManager->getPathId("/editor/reviewer");
        $this->assertSame("/editor/reviewer", $this->roleManager->getPath($id));
    }

    public function testGetByIdNotFound(): void
    {
        $this->expectException(RbacRoleNotFoundException::class);
        $this->roleManager->getNode(999);
    }

    public function testGetPathIdNotFound(): void
    {
        $this->expectException(RbacRoleNotFoundException::class);
        $this->roleManager->getPathId("/editor/reviewer");
    }

    public function testAssignPermission(): void
    {
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

        $this->roleManager->addPath("/editor/reviewer", ["editor" => "Editor", "reviewer" => "Reviewer"]);

        $editorId = $this->roleManager->getPathId("/editor");
        $editor = $this->roleManager->getNode($editorId);
        $reviewerId = $this->roleManager->getPathId("/editor/reviewer");
        $reviewer = $this->roleManager->getNode($reviewerId);

        $this->roleManager->assignPermission($editor, "/notepad");
        $this->roleManager->assignPermission($reviewer, "/notepad/todolist/read");
        $this->roleManager->assignPermission($reviewer, "/notepad/todolist/write");

        $perm1 = $this->permissionManager->getPathId("/notepad");
        $perm2 = $this->permissionManager->getPathId("/notepad/todolist");
        $perm3 = $this->permissionManager->getPathId("/notepad/todolist/read");
        $perm4 = $this->permissionManager->getPathId("/notepad/todolist/write");

        $this->assertTrue($this->roleManager->hasPermission($editor->getId(), $perm1));
        $this->assertTrue($this->roleManager->hasPermission($editor->getId(), $perm2));
        $this->assertTrue($this->roleManager->hasPermission($editor->getId(), $perm3));
        $this->assertTrue($this->roleManager->hasPermission($editor->getId(), $perm4));
        $this->assertFalse($this->roleManager->hasPermission($editor->getId(), NodeManagerInterface::ROOT_ID));

        $this->assertTrue($this->roleManager->hasPermission($reviewer->getId(), $perm3));
        $this->assertTrue($this->roleManager->hasPermission($reviewer->getId(), $perm4));

        $this->assertFalse($this->roleManager->hasPermission($reviewer->getId(), $perm2));
    }

    public function testUnassignPermission(): void
    {
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

        $this->roleManager->addPath("/editor/reviewer", ["editor" => "Editor", "reviewer" => "Reviewer"]);

        $editorId = $this->roleManager->getPathId("/editor");
        $editor = $this->roleManager->getNode($editorId);
        $reviewerId = $this->roleManager->getPathId("/editor/reviewer");
        $reviewer = $this->roleManager->getNode($reviewerId);

        $this->roleManager->assignPermission($editor, "/notepad");
        $this->roleManager->assignPermission($reviewer, "/notepad/todolist/read");
        $this->roleManager->assignPermission($reviewer, "/notepad/todolist/write");

        $perm4 = $this->permissionManager->getPathId("/notepad/todolist/write");
        $this->assertTrue($this->roleManager->hasPermission($reviewer->getId(), $perm4));
        $this->roleManager->unassignPermission($reviewer, "/notepad/todolist/write");
        $this->assertFalse($this->roleManager->hasPermission($reviewer->getId(), $perm4));
        $this->roleManager->assignPermission($reviewer, "/notepad/todolist/write");
        $this->assertTrue($this->roleManager->hasPermission($reviewer->getId(), $perm4));
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
