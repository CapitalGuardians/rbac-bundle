<?php

namespace Tests\PhpRbacBundle;

use Exception;
use PhpRbacBundle\Core\Manager\PermissionManager;
use PhpRbacBundle\Core\Manager\NodeManagerInterface;
use PhpRbacBundle\Exception\RbacPermissionNotFoundException;
use PhpRbacBundle\Repository\PermissionRepository;
use PhpRbacBundle\Repository\RoleRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\PhpRbacBundle\Fixtures\TestKernel;

class PermissionTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }
    private PermissionRepository $permissionRepository;
    private RoleRepository $roleRepository;
    private PermissionManager $permissionManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->permissionRepository = $container->get(PermissionRepository::class);
        $this->roleRepository = $container->get(RoleRepository::class);
        $this->permissionManager = $container->get(PermissionManager::class);

        // Initialize tables for each test
        $this->permissionRepository->initTable();
        $this->roleRepository->initTable();
    }

    public function testSearchPermission(): void
    {
        try
        {
            $permissionId = $this->permissionManager->getPathId("/");
            $this->assertEquals(NodeManagerInterface::ROOT_ID, $permissionId);
        }
        catch (Exception $e)
        {
            $this->fail($e->getMessage());
        }
    }

    public function testAddPermission(): void
    {
        $permission = $this->permissionManager->add("Notepad", "Notepad", NodeManagerInterface::ROOT_ID);

        $this->assertGreaterThan(0, $permission->getId());
        $this->assertSame('notepad', $permission->getCode());
        $this->assertSame('Notepad', $permission->getDescription());

        $path = $this->permissionManager->getPath($permission->getId());
        $this->assertSame("/notepad", $path);

        $permissionId = $this->permissionManager->getPathId("/notepad");
        $this->assertSame($permission->getId(), $permissionId);
    }

    /**
     * @depends testAddPermission
     */
    public function testAddDoublePermission(): void
    {
        $permission1 = $this->permissionManager->add("Notepad", "Notepad", NodeManagerInterface::ROOT_ID);
        $permission2 = $this->permissionManager->add("Notepad", "Notepad", NodeManagerInterface::ROOT_ID);

        $this->assertSame($permission1->getId(), $permission2->getId());
    }

    /**
     * @depends testAddPermission
     */
    public function testAddSubPermission(): void
    {
        $permission = $this->permissionManager->add("notepad", "Notepad", NodeManagerInterface::ROOT_ID);
        $subPermission = $this->permissionManager->add("todolist", "Todo list", $permission->getId());

        $this->assertGreaterThan($permission->getId(), $subPermission->getId(), "Error ID");
        $this->assertGreaterThan($permission->getLeft(), $subPermission->getLeft(), "Error left");
        $this->assertLessThan($permission->getRight(), $subPermission->getRight(), "Error Right {$permission->getRight()} {$subPermission->getRight()}");
    }

    /**
     * @depends testAddSubPermission
     */
    public function testAddDoubleSubPermission(): void
    {
        $permission = $this->permissionManager->add("notepad", "Notepad", NodeManagerInterface::ROOT_ID);
        $subPermission1 = $this->permissionManager->add("todolist", "Todo list", $permission->getId());
        $subPermission2 = $this->permissionManager->add("todolist", "Todo list", $permission->getId());

        $this->assertSame($subPermission1->getId(), $subPermission2->getId());
    }

    public function testAddPath(): void
    {
        $permission = $this->permissionManager->addPath("/notepad/todolist/read", [
            'notepad' => 'Notepad',
            'todolist' => "Todo list",
            "read" => "Read Access"
        ]);

        $this->assertGreaterThan(0, $permission->getId());
        $this->assertSame('read', $permission->getCode());
        $this->assertSame('Read Access', $permission->getDescription());

        $subPermission = $this->permissionManager->getNode($this->permissionManager->getPathId('/notepad/todolist/read'));
        $permission = $this->permissionManager->getNode($this->permissionManager->getPathId('/notepad/todolist'));

        $this->assertGreaterThan($permission->getId(), $subPermission->getId(), "Error ID");
        $this->assertGreaterThan($permission->getLeft(), $subPermission->getLeft(), "Error left");
        $this->assertLessThan($permission->getRight(), $subPermission->getRight(), "Error Right {$permission->getRight()} {$subPermission->getRight()}");
    }

    public function testPath(): void
    {
        $this->permissionManager->addPath("/notepad/todolist/read", [
            'notepad' => 'Notepad',
            'todolist' => "Todo list",
            "read" => "Read Access"
        ]);

        $id = $this->permissionManager->getPathId("/notepad/todolist/read");
        $this->assertSame("/notepad/todolist/read", $this->permissionManager->getPath($id));
    }

    public function testGetByIdNotFound(): void
    {
        $this->expectException(RbacPermissionNotFoundException::class);
        $this->permissionManager->getNode(999);
    }

    public function testGetPathIdNotFound(): void
    {
        $this->expectException(RbacPermissionNotFoundException::class);
        $this->permissionManager->getPathId("/nonexistent/path");
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
