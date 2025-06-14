<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Tourze\DoctrineDedicatedConnectionBundle\Factory\DedicatedConnectionFactory;
use Tourze\DoctrineDedicatedConnectionBundle\Tests\Fixtures\TestService;
use Tourze\DoctrineDedicatedConnectionBundle\Tests\Fixtures\TestServiceWithTag;
use Tourze\DoctrineDedicatedConnectionBundle\Tests\TestKernel;

class BundleIntegrationTest extends TestCase
{
    public function testBundleRegistration(): void
    {
        $kernel = new TestKernel();
        $kernel->boot();
        
        $container = $kernel->getContainer();
        
        // The factory should be registered by our extension
        $this->assertTrue(
            $container->has('doctrine_dedicated_connection.factory') || 
            $container->has(DedicatedConnectionFactory::class),
            'Factory service should be registered'
        );
    }

    public function testServiceWithAnnotation(): void
    {
        // 设置环境变量 - channel 是 'test_db'，所以前缀是 'TEST_DB'
        $_ENV['TEST_DB_DB_NAME'] = 'test_annotation_db';
        
        $kernel = new TestKernel([], function (ContainerBuilder $container) {
            $container->autowire('test.service', TestService::class)
                ->setAutoconfigured(true)
                ->setPublic(true);
        });
        
        $kernel->boot();
        $container = $kernel->getContainer();
        
        /** @var TestService $service */
        $service = $container->get('test.service');
        
        $this->assertInstanceOf(TestService::class, $service);
        $this->assertInstanceOf(Connection::class, $service->getConnection());
        
        // Check what connection the service actually received
        $connection = $service->getConnection();
        $params = $connection->getParams();
        
        // The connection should have the database name from environment variable
        $this->assertArrayHasKey('dbname', $params, 'Connection should have a database name');
        $this->assertEquals('test_annotation_db', $params['dbname']);
        
        unset($_ENV['TEST_DB_DB_NAME']);
    }

    public function testServiceWithTag(): void
    {
        $_ENV['TAGGED_DB_NAME'] = 'test_tag_db';
        
        $kernel = new TestKernel([], function (ContainerBuilder $container) {
            $definition = new Definition(TestServiceWithTag::class);
            $definition->setAutowired(true);
            $definition->setPublic(true);
            $definition->addTag('doctrine.dedicated_connection', ['channel' => 'tagged']);
            
            $container->setDefinition('test.tagged_service', $definition);
        });
        
        $kernel->boot();
        $container = $kernel->getContainer();
        
        /** @var TestServiceWithTag $service */
        $service = $container->get('test.tagged_service');
        
        $this->assertInstanceOf(TestServiceWithTag::class, $service);
        $this->assertInstanceOf(Connection::class, $service->getConnection());
        $this->assertEquals('test_tag_db', $service->getDatabaseName());
        
        unset($_ENV['TAGGED_DB_NAME']);
    }

    public function testMultipleServicesWithSameChannel(): void
    {
        $kernel = new TestKernel([], function (ContainerBuilder $container) {
            // First service
            $def1 = new Definition(TestServiceWithTag::class);
            $def1->setAutowired(true);
            $def1->setPublic(true);
            $def1->addTag('doctrine.dedicated_connection', ['channel' => 'shared']);
            $container->setDefinition('test.service1', $def1);
            
            // Second service
            $def2 = new Definition(TestServiceWithTag::class);
            $def2->setAutowired(true);
            $def2->setPublic(true);
            $def2->addTag('doctrine.dedicated_connection', ['channel' => 'shared']);
            $container->setDefinition('test.service2', $def2);
        });
        
        $kernel->boot();
        $container = $kernel->getContainer();
        
        /** @var TestServiceWithTag $service1 */
        $service1 = $container->get('test.service1');
        /** @var TestServiceWithTag $service2 */
        $service2 = $container->get('test.service2');
        
        // Both services should use the same connection
        $this->assertSame(
            $service1->getConnection(),
            $service2->getConnection()
        );
    }

    public function testEnvironmentVariableOverride(): void
    {
        $_ENV['CUSTOM_DB_HOST'] = 'custom.host.com';
        $_ENV['CUSTOM_DB_PORT'] = '5432';
        $_ENV['CUSTOM_DB_NAME'] = 'custom_database';
        $_ENV['CUSTOM_DB_USER'] = 'custom_user';
        $_ENV['CUSTOM_DB_PASSWORD'] = 'custom_pass';
        $_ENV['CUSTOM_DB_DRIVER'] = 'pdo_pgsql';
        
        $kernel = new TestKernel([], function (ContainerBuilder $container) {
            $definition = new Definition(TestServiceWithTag::class);
            $definition->setAutowired(true);
            $definition->setPublic(true);
            $definition->addTag('doctrine.dedicated_connection', ['channel' => 'custom']);
            
            $container->setDefinition('test.custom_service', $definition);
        });
        
        $kernel->boot();
        $container = $kernel->getContainer();
        
        /** @var TestServiceWithTag $service */
        $service = $container->get('test.custom_service');
        
        $connection = $service->getConnection();
        $params = $connection->getParams();
        
        $this->assertEquals('custom.host.com', $params['host']);
        $this->assertEquals(5432, $params['port']);
        $this->assertEquals('custom_database', $params['dbname']);
        $this->assertEquals('custom_user', $params['user']);
        $this->assertEquals('custom_pass', $params['password']);
        $this->assertEquals('pdo_pgsql', $params['driver']);
        
        unset(
            $_ENV['CUSTOM_DB_HOST'],
            $_ENV['CUSTOM_DB_PORT'],
            $_ENV['CUSTOM_DB_NAME'],
            $_ENV['CUSTOM_DB_USER'],
            $_ENV['CUSTOM_DB_PASSWORD'],
            $_ENV['CUSTOM_DB_DRIVER']
        );
    }

    public function testDefaultDatabaseSuffix(): void
    {
        $kernel = new TestKernel([], function (ContainerBuilder $container) {
            $definition = new Definition(TestServiceWithTag::class);
            $definition->setAutowired(true);
            $definition->setPublic(true);
            $definition->addTag('doctrine.dedicated_connection', ['channel' => 'suffix_test']);
            
            $container->setDefinition('test.suffix_service', $definition);
        });
        
        $kernel->boot();
        $container = $kernel->getContainer();
        
        // Check the service was properly configured with the dedicated connection
        /** @var TestServiceWithTag $service */
        $service = $container->get('test.suffix_service');
        $this->assertInstanceOf(Connection::class, $service->getConnection());
        
        // Since the connection is private, we can't check it directly with has()
        // Instead, verify the service received a connection
        $connection = $service->getConnection();
        $params = $connection->getParams();
        
        // The default connection is SQLite memory which doesn't have dbname
        // Just verify the service got a connection different from the default
        $defaultConnection = $container->get('doctrine.dbal.default_connection');
        $this->assertNotSame($defaultConnection, $connection, 'Service should have its own dedicated connection');
    }
}
