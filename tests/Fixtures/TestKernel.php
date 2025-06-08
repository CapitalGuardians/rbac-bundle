<?php

namespace Tests\PhpRbacBundle\Fixtures;

use PhpRbacBundle\PhpRbacBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;

class TestKernel extends Kernel
{
    public function __construct()
    {
        parent::__construct('test', true);
    }

    /**
     * @return BundleInterface[]
     */
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new PhpRbacBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container)
        {
            $container->loadFromExtension('framework', [
                'test' => true,
                'secret' => 'test-secret',
            ]);

            $container->loadFromExtension('doctrine', [
                'dbal' => [
                    'url' => $_ENV['DATABASE_URL'] ?? 'sqlite:///:memory:',
                    'logging' => false,
                ],
                'orm' => [
                    'auto_generate_proxy_classes' => true,
                    'auto_mapping' => true,
                    'mappings' => [
                        'PhpRbacBundle' => [
                            'is_bundle' => true,
                            'type' => 'attribute',
                            'dir' => 'Entity',
                            'prefix' => 'PhpRbacBundle\Entity',
                        ],
                        'TestEntities' => [
                            'is_bundle' => false,
                            'type' => 'attribute',
                            'dir' => dirname(__DIR__),
                            'prefix' => 'Tests\PhpRbacBundle',
                        ],
                    ],
                ],
            ]);

            $container->loadFromExtension('php_rbac', [
                'resolve_target_entities' => [
                    'user' => 'Tests\PhpRbacBundle\Role\TestUser',
                    'role' => 'Tests\PhpRbacBundle\Role\TestRole',
                    'permission' => 'Tests\PhpRbacBundle\Permission\TestPermission',
                ],
            ]);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/phprbac_test_cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/phprbac_test_logs';
    }
}
