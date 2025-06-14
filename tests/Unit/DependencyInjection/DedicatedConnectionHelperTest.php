<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Tourze\DoctrineDedicatedConnectionBundle\DependencyInjection\DedicatedConnectionHelper;
use Tourze\DoctrineDedicatedConnectionBundle\Factory\DedicatedConnectionFactory;

class DedicatedConnectionHelperTest extends TestCase
{
    private ContainerBuilder $container;

    public function testCreateConnection(): void
    {
        $connectionId = DedicatedConnectionHelper::createConnection($this->container, 'test');

        $this->assertEquals('doctrine.dbal.test_connection', $connectionId);
        $this->assertTrue($this->container->hasDefinition($connectionId));

        $definition = $this->container->getDefinition($connectionId);
        $this->assertEquals(['doctrine_dedicated_connection.factory', 'createConnection'], $definition->getFactory());
        $this->assertEquals(['test'], $definition->getArguments());
    }

    public function testCreateConnectionReturnsSameIdIfExists(): void
    {
        $connectionId1 = DedicatedConnectionHelper::createConnection($this->container, 'test');
        $connectionId2 = DedicatedConnectionHelper::createConnection($this->container, 'test');

        $this->assertEquals($connectionId1, $connectionId2);
    }

    public function testCreateConnectionThrowsIfFactoryMissing(): void
    {
        $container = new ContainerBuilder();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The doctrine_dedicated_connection.factory service is not defined');

        DedicatedConnectionHelper::createConnection($container, 'test');
    }

    public function testHasConnection(): void
    {
        $this->assertFalse(DedicatedConnectionHelper::hasConnection($this->container, 'test'));

        DedicatedConnectionHelper::createConnection($this->container, 'test');

        $this->assertTrue(DedicatedConnectionHelper::hasConnection($this->container, 'test'));
    }

    public function testGetConnectionId(): void
    {
        $this->assertEquals('doctrine.dbal.test_connection', DedicatedConnectionHelper::getConnectionId('test'));
        $this->assertEquals('doctrine.dbal.order_connection', DedicatedConnectionHelper::getConnectionId('order'));
    }

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();

        // Add factory service
        $factoryDef = new Definition(DedicatedConnectionFactory::class);
        $this->container->setDefinition('doctrine_dedicated_connection.factory', $factoryDef);
    }
}