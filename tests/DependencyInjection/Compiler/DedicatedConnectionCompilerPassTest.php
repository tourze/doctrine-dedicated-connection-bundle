<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\Tests\DependencyInjection\Compiler;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Tourze\DoctrineDedicatedConnectionBundle\DependencyInjection\Compiler\DedicatedConnectionCompilerPass;
use Tourze\DoctrineDedicatedConnectionBundle\Exception\InvalidArgumentException;

class DedicatedConnectionCompilerPassTest extends TestCase
{
    private DedicatedConnectionCompilerPass $pass;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->pass = new DedicatedConnectionCompilerPass();
        $this->container = new ContainerBuilder();
        
        // 设置默认连接
        $defaultConnection = new Definition(Connection::class);
        $this->container->setDefinition('doctrine.dbal.default_connection', $defaultConnection);
    }

    public function testProcessWithoutDefaultConnection(): void
    {
        $container = new ContainerBuilder();
        
        // 不应该抛出异常
        $this->pass->process($container);
        
        $this->assertTrue(true); // Pass 成功完成
    }

    public function testProcessWithValidTag(): void
    {
        $serviceDefinition = new Definition('TestService');
        $serviceDefinition->addTag('doctrine.dedicated_connection', ['channel' => 'test']);
        
        $this->container->setDefinition('test.service', $serviceDefinition);
        
        $this->pass->process($this->container);
        
        // 验证连接服务被创建
        $this->assertTrue($this->container->hasDefinition('doctrine.dbal.test_connection'));
        
        // 验证参数被添加
        $arguments = $this->container->getDefinition('test.service')->getArguments();
        $this->assertCount(1, $arguments);
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertEquals('doctrine.dbal.test_connection', (string) $arguments[0]);
    }

    public function testProcessWithMissingChannelAttribute(): void
    {
        $serviceDefinition = new Definition('TestService');
        $serviceDefinition->addTag('doctrine.dedicated_connection');
        
        $this->container->setDefinition('test.service', $serviceDefinition);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "doctrine.dedicated_connection" tag on service "test.service" must have a "channel" attribute.');
        
        $this->pass->process($this->container);
    }

    public function testProcessReplacesExistingConnectionArgument(): void
    {
        $serviceDefinition = new Definition('TestService');
        $serviceDefinition->addTag('doctrine.dedicated_connection', ['channel' => 'test']);
        $serviceDefinition->addArgument(new Reference('doctrine.dbal.default_connection'));
        
        $this->container->setDefinition('test.service', $serviceDefinition);
        
        $this->pass->process($this->container);
        
        // 验证参数被替换
        $arguments = $this->container->getDefinition('test.service')->getArguments();
        $this->assertCount(1, $arguments);
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertEquals('doctrine.dbal.test_connection', (string) $arguments[0]);
    }

    public function testProcessWithMultipleServices(): void
    {
        $service1 = new Definition('TestService1');
        $service1->addTag('doctrine.dedicated_connection', ['channel' => 'db1']);
        $this->container->setDefinition('test.service1', $service1);
        
        $service2 = new Definition('TestService2');
        $service2->addTag('doctrine.dedicated_connection', ['channel' => 'db2']);
        $this->container->setDefinition('test.service2', $service2);
        
        $this->pass->process($this->container);
        
        // 验证两个连接都被创建
        $this->assertTrue($this->container->hasDefinition('doctrine.dbal.db1_connection'));
        $this->assertTrue($this->container->hasDefinition('doctrine.dbal.db2_connection'));
    }
}