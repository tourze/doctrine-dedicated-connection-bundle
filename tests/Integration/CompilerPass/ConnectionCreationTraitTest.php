<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\Tests\Integration\CompilerPass;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Tourze\DoctrineDedicatedConnectionBundle\DependencyInjection\Compiler\ConnectionCreationTrait;
use Tourze\DoctrineDedicatedConnectionBundle\Tests\Fixtures\TestServiceWithTag;
use Tourze\DoctrineDedicatedConnectionBundle\Tests\TestKernel;

class ConnectionCreationTraitTest extends TestCase
{
    public function testTraitUsageInCustomCompilerPass(): void
    {
        $compilerPass = new class implements CompilerPassInterface {
            use ConnectionCreationTrait;
            
            public function process(ContainerBuilder $container): void
            {
                // Use the trait method to create a connection
                $this->ensureConnectionService($container, 'trait_test');
                
                // Verify it was created
                if (!$container->hasDefinition('doctrine.dbal.trait_test_connection')) {
                    throw new \RuntimeException('Connection was not created by trait');
                }
            }
        };
        
        // Add a test service that will use the connection
        $kernel = new TestKernel([], function (ContainerBuilder $container) {
            $definition = new Definition(TestServiceWithTag::class);
            $definition->setArguments([new Reference('doctrine.dbal.trait_test_connection')]);
            $definition->setPublic(true);
            $container->setDefinition('test.trait_service', $definition);
        });
        
        $kernel->addCompilerPass($compilerPass);
        $kernel->boot();
        
        $container = $kernel->getContainer();
        
        // Verify the connection was created by checking if the service can be instantiated
        /** @var TestServiceWithTag $service */
        $service = $container->get('test.trait_service');
        $this->assertInstanceOf(Connection::class, $service->getConnection());
    }

    public function testTraitDoesNotRecreateExistingConnection(): void
    {
        $firstPass = new class implements CompilerPassInterface {
            use ConnectionCreationTrait;
            
            public function process(ContainerBuilder $container): void
            {
                $this->ensureConnectionService($container, 'duplicate_test');
                
                // Mark the definition to track if it's recreated
                $container->getDefinition('doctrine.dbal.duplicate_test_connection')
                    ->addTag('test.first_pass');
            }
        };
        
        $secondPass = new class implements CompilerPassInterface {
            use ConnectionCreationTrait;
            
            public function process(ContainerBuilder $container): void
            {
                // Try to create the same connection again
                $this->ensureConnectionService($container, 'duplicate_test');
                
                // Check that it still has the tag from the first pass
                $definition = $container->getDefinition('doctrine.dbal.duplicate_test_connection');
                if (!$definition->hasTag('test.first_pass')) {
                    throw new \RuntimeException('Connection was recreated');
                }
            }
        };
        
        $kernel = new TestKernel();
        $kernel->addCompilerPass($firstPass);
        $kernel->addCompilerPass($secondPass);
        $kernel->boot();
        
        // If we reach here without exceptions, the test passes
        $this->assertTrue(true);
    }
}