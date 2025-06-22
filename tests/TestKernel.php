<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Tourze\DoctrineDedicatedConnectionBundle\DoctrineDedicatedConnectionBundle;
use Tourze\Symfony\RuntimeContextBundle\RuntimeContextBundle;

class TestKernel extends Kernel
{
    private array $extraBundles;
    private ?\Closure $configureContainer;
    private array $compilerPasses = [];

    public function __construct(array $extraBundles = [], ?\Closure $configureContainer = null)
    {
        $this->extraBundles = $extraBundles;
        $this->configureContainer = $configureContainer;
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        $bundles = [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new RuntimeContextBundle(),
            new DoctrineDedicatedConnectionBundle(),
        ];

        return array_merge($bundles, $this->extraBundles);
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->setParameter('kernel.secret', 'test');

            // Framework configuration
            $container->loadFromExtension('framework', [
                'test' => true,
                'secret' => 'test',
                'handle_all_throwables' => true,
                'http_method_override' => false,
                'php_errors' => [
                    'log' => true,
                ],
                'router' => [
                    'resource' => '%kernel.project_dir%/config/routes.yaml',
                    'utf8' => true,
                ],
            ]);

            // Doctrine configuration
            $container->loadFromExtension('doctrine', [
                'dbal' => [
                    'default_connection' => 'default',
                    'connections' => [
                        'default' => [
                            'driver' => 'pdo_sqlite',
                            'memory' => true,
                            'charset' => 'utf8mb4',
                        ],
                    ],
                ],
            ]);

            // Allow custom configuration
            if ($this->configureContainer !== null) {
                ($this->configureContainer)($container);
            }
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/doctrine_dedicated_connection_bundle/cache/' . spl_object_hash($this);
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/doctrine_dedicated_connection_bundle/logs';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        foreach ($this->compilerPasses as $compilerPass) {
            $container->addCompilerPass($compilerPass);
        }
    }

    public function addCompilerPass($compilerPass): void
    {
        $this->compilerPasses[] = $compilerPass;
    }
}
