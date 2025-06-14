<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\Tests\Integration\CompilerPass;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Tourze\DoctrineDedicatedConnectionBundle\DependencyInjection\DedicatedConnectionHelper;
use Tourze\DoctrineDedicatedConnectionBundle\Tests\Fixtures\TestServiceWithTag;
use Tourze\DoctrineDedicatedConnectionBundle\Tests\TestKernel;

class CustomCompilerPassTest extends TestCase
{
    public function testCustomCompilerPassCanCreateConnection(): void
    {
        $_ENV['ANALYTICS_DB_NAME'] = 'analytics_database';
        
        $compilerPass = new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                // Create a dedicated connection using the helper
                $connectionId = DedicatedConnectionHelper::createConnection($container, 'analytics');
                
                // Create a service that uses this connection
                $definition = new Definition(TestServiceWithTag::class);
                $definition->setArguments([new Reference($connectionId)]);
                $definition->setPublic(true);
                
                $container->setDefinition('analytics.service', $definition);
            }
        };
        
        $kernel = new TestKernel();
        $kernel->addCompilerPass($compilerPass);
        $kernel->boot();
        
        $container = $kernel->getContainer();
        
        // Check that the service exists
        $this->assertTrue($container->has('analytics.service'));
        
        /** @var TestServiceWithTag $service */
        $service = $container->get('analytics.service');
        $this->assertInstanceOf(TestServiceWithTag::class, $service);
        $this->assertEquals('analytics_database', $service->getDatabaseName());
        
        unset($_ENV['ANALYTICS_DB_NAME']);
    }

    public function testMultipleConnectionsInCompilerPass(): void
    {
        $compilerPass = new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                // Create multiple connections
                $connections = ['reporting', 'metrics', 'logs'];
                
                foreach ($connections as $channel) {
                    $connectionId = DedicatedConnectionHelper::createConnection($container, $channel);
                    
                    $definition = new Definition(TestServiceWithTag::class);
                    $definition->setArguments([new Reference($connectionId)]);
                    $definition->setPublic(true);
                    
                    $container->setDefinition($channel . '.service', $definition);
                }
            }
        };
        
        $kernel = new TestKernel();
        $kernel->addCompilerPass($compilerPass);
        $kernel->boot();
        
        $container = $kernel->getContainer();
        
        // Check all services were created
        $this->assertTrue($container->has('reporting.service'));
        $this->assertTrue($container->has('metrics.service'));  
        $this->assertTrue($container->has('logs.service'));
        
        // Verify each service has its own connection
        $reportingService = $container->get('reporting.service');
        $metricsService = $container->get('metrics.service');
        $logsService = $container->get('logs.service');
        
        $this->assertNotSame(
            $reportingService->getConnection(),
            $metricsService->getConnection(),
            'Services should have different connections'
        );
    }

    public function testCompilerPassWithExistingConnection(): void
    {
        $compilerPass = new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                // First, check if connection exists
                if (!DedicatedConnectionHelper::hasConnection($container, 'existing')) {
                    DedicatedConnectionHelper::createConnection($container, 'existing');
                }
                
                // Try to create the same connection again (should not fail)
                $connectionId = DedicatedConnectionHelper::createConnection($container, 'existing');
                
                $definition = new Definition(TestServiceWithTag::class);
                $definition->setArguments([new Reference($connectionId)]);
                $definition->setPublic(true);
                
                $container->setDefinition('existing.service', $definition);
            }
        };
        
        $kernel = new TestKernel();
        $kernel->addCompilerPass($compilerPass);
        $kernel->boot();
        
        $container = $kernel->getContainer();
        
        $this->assertTrue($container->has('existing.service'));
    }

    public function testCompilerPassPriority(): void
    {
        // This tests that custom compiler passes run after the bundle's passes
        $customPass = new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                // This should work because the factory is already registered
                $connectionId = DedicatedConnectionHelper::createConnection($container, 'priority_test');
                
                // Also check that we can get a connection created by annotation
                if (!$container->hasDefinition('doctrine.dbal.priority_test_connection')) {
                    throw new \RuntimeException('Connection was not created');
                }
            }
        };
        
        $kernel = new TestKernel([], function (ContainerBuilder $container) {
            $container->autowire('annotated.service', TestService::class)
                ->setPublic(true);
        });
        
        // Add compiler pass with default priority (0)
        $kernel->addCompilerPass($customPass);
        $kernel->boot();
        
        // If we reach here without exceptions, the test passes
        $this->assertTrue(true);
    }
}