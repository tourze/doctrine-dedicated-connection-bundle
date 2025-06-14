<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Tourze\DoctrineDedicatedConnectionBundle\Tests\Fixtures\TestServiceWithTag;
use Tourze\DoctrineDedicatedConnectionBundle\Tests\TestKernel;

class EdgeCasesTest extends TestCase
{
    public function testServiceWithoutConnectionParameter(): void
    {
        $kernel = new TestKernel([], function (ContainerBuilder $container) {
            // Service without any Connection parameter
            $definition = new Definition(ServiceWithoutConnection::class);
            $definition->setPublic(true);
            $definition->addTag('doctrine.dedicated_connection', ['channel' => 'no_param']);
            
            $container->setDefinition('test.no_param_service', $definition);
        });
        
        $kernel->boot();
        $container = $kernel->getContainer();
        
        /** @var ServiceWithoutConnection $service */
        $service = $container->get('test.no_param_service');
        
        // Service should be created with the connection added as an argument
        $this->assertInstanceOf(ServiceWithoutConnection::class, $service);
        $this->assertNotNull($service->getInjectedConnection());
    }

    public function testServiceWithMultipleConnectionParameters(): void
    {
        $kernel = new TestKernel([], function (ContainerBuilder $container) {
            // Service with multiple Connection parameters
            $definition = new Definition(ServiceWithMultipleConnections::class);
            $definition->setArguments([
                new Reference('doctrine.dbal.default_connection'),
                new Reference('doctrine.dbal.default_connection')
            ]);
            $definition->setPublic(true);
            $definition->addTag('doctrine.dedicated_connection', ['channel' => 'multi']);
            
            $container->setDefinition('test.multi_service', $definition);
        });
        
        $kernel->boot();
        $container = $kernel->getContainer();
        
        /** @var ServiceWithMultipleConnections $service */
        $service = $container->get('test.multi_service');
        
        // First connection should be replaced with dedicated one
        $this->assertNotSame($service->getConnection1(), $service->getConnection2());
    }

    public function testInvalidChannelName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $kernel = new TestKernel([], function (ContainerBuilder $container) {
            $definition = new Definition(TestServiceWithTag::class);
            $definition->setPublic(true);
            $definition->addTag('doctrine.dedicated_connection', []); // Missing channel
            
            $container->setDefinition('test.invalid_service', $definition);
        });
        
        $kernel->boot();
    }

    public function testSpecialCharactersInChannelName(): void
    {
        $_ENV['SPECIAL_CHARS_DB_NAME'] = 'special_db';
        
        $kernel = new TestKernel([], function (ContainerBuilder $container) {
            $definition = new Definition(TestServiceWithTag::class);
            $definition->setAutowired(true);
            $definition->setPublic(true);
            $definition->addTag('doctrine.dedicated_connection', ['channel' => 'special_chars']);
            
            $container->setDefinition('test.special_service', $definition);
        });
        
        $kernel->boot();
        $container = $kernel->getContainer();
        
        // Verify the connection works by checking the service
        /** @var TestServiceWithTag $service */
        $service = $container->get('test.special_service');
        $this->assertInstanceOf(TestServiceWithTag::class, $service);
        $this->assertEquals('special_db', $service->getDatabaseName());
        
        unset($_ENV['SPECIAL_CHARS_DB_NAME']);
    }

    public function testVeryLongChannelName(): void
    {
        $longChannel = str_repeat('a', 100);
        
        $kernel = new TestKernel([], function (ContainerBuilder $container) use ($longChannel) {
            $definition = new Definition(TestServiceWithTag::class);
            $definition->setAutowired(true);
            $definition->setPublic(true);
            $definition->addTag('doctrine.dedicated_connection', ['channel' => $longChannel]);
            
            $container->setDefinition('test.long_channel_service', $definition);
        });
        
        $kernel->boot();
        $container = $kernel->getContainer();
        
        // Verify the connection works even with a very long channel name
        /** @var TestServiceWithTag $service */
        $service = $container->get('test.long_channel_service');
        $this->assertInstanceOf(TestServiceWithTag::class, $service);
        
        // The service should have received a dedicated connection
        $defaultConnection = $container->get('doctrine.dbal.default_connection');
        $this->assertNotSame($defaultConnection, $service->getConnection());
    }
}

class ServiceWithoutConnection
{
    private $injectedConnection;
    
    public function __construct($connection = null)
    {
        $this->injectedConnection = $connection;
    }
    
    public function getInjectedConnection()
    {
        return $this->injectedConnection;
    }
}

class ServiceWithMultipleConnections
{
    private $connection1;
    private $connection2;
    
    public function __construct($connection1, $connection2)
    {
        $this->connection1 = $connection1;
        $this->connection2 = $connection2;
    }
    
    public function getConnection1()
    {
        return $this->connection1;
    }
    
    public function getConnection2()
    {
        return $this->connection2;
    }
}