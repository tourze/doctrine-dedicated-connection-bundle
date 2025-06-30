<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\Tests\DependencyInjection\Compiler;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Tourze\DoctrineDedicatedConnectionBundle\DependencyInjection\Compiler\ConnectionChannelPass;
use Tourze\DoctrineDedicatedConnectionBundle\Exception\InvalidArgumentException;

class ConnectionChannelPassTest extends TestCase
{
    private ConnectionChannelPass $pass;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->pass = new ConnectionChannelPass();
        $this->container = new ContainerBuilder();
    }

    public function testProcessWithValidTag(): void
    {
        // 创建一个带有 Connection 参数的服务定义
        $serviceDefinition = new Definition(TestServiceWithConnection::class);
        $serviceDefinition->addTag('doctrine.dedicated_connection', ['channel' => 'test']);
        $serviceDefinition->setArguments([null]); // Connection 参数
        
        $this->container->setDefinition('test.service', $serviceDefinition);
        
        // 设置默认连接
        $defaultConnection = new Definition(Connection::class);
        $this->container->setDefinition('doctrine.dbal.default_connection', $defaultConnection);
        
        // 运行 pass
        $this->pass->process($this->container);
        
        // 验证连接参数被正确设置
        $arguments = $this->container->getDefinition('test.service')->getArguments();
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertEquals('doctrine.dbal.test_connection', (string) $arguments[0]);
    }

    public function testProcessWithMissingChannelAttribute(): void
    {
        $serviceDefinition = new Definition(TestServiceWithConnection::class);
        $serviceDefinition->addTag('doctrine.dedicated_connection'); // 没有 channel 属性
        
        $this->container->setDefinition('test.service', $serviceDefinition);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service "test.service" has a "doctrine.dedicated_connection" tag without a "channel" attribute.');
        
        $this->pass->process($this->container);
    }

    public function testProcessWithServiceWithoutConnectionParameter(): void
    {
        $serviceDefinition = new Definition(TestServiceWithoutConnection::class);
        $serviceDefinition->addTag('doctrine.dedicated_connection', ['channel' => 'test']);
        
        $this->container->setDefinition('test.service', $serviceDefinition);
        
        // 设置默认连接
        $defaultConnection = new Definition(Connection::class);
        $this->container->setDefinition('doctrine.dbal.default_connection', $defaultConnection);
        
        // 不应该抛出异常
        $this->pass->process($this->container);
        
        // 验证没有参数被添加
        $this->assertEmpty($this->container->getDefinition('test.service')->getArguments());
    }

    public function testProcessWithNullClass(): void
    {
        $serviceDefinition = new Definition();
        $serviceDefinition->addTag('doctrine.dedicated_connection', ['channel' => 'test']);
        
        $this->container->setDefinition('test.service', $serviceDefinition);
        
        // 设置默认连接
        $defaultConnection = new Definition(Connection::class);
        $this->container->setDefinition('doctrine.dbal.default_connection', $defaultConnection);
        
        // 不应该抛出异常
        $this->pass->process($this->container);
        
        // 验证连接服务被创建
        $this->assertTrue($this->container->hasDefinition('doctrine.dbal.test_connection'));
    }
}

/**
 * @internal
 */
class TestServiceWithConnection
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }
}

/**
 * @internal
 */
class TestServiceWithoutConnection
{
    public function __construct()
    {
    }
}