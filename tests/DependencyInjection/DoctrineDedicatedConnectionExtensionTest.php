<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\DoctrineDedicatedConnectionBundle\Attribute\WithDedicatedConnection;
use Tourze\DoctrineDedicatedConnectionBundle\DependencyInjection\DoctrineDedicatedConnectionExtension;
use Tourze\DoctrineDedicatedConnectionBundle\Factory\DedicatedConnectionFactory;

class DoctrineDedicatedConnectionExtensionTest extends TestCase
{
    private DoctrineDedicatedConnectionExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new DoctrineDedicatedConnectionExtension();
        $this->container = new ContainerBuilder();
    }

    public function testGetAlias(): void
    {
        $this->assertEquals('doctrine_dedicated_connection', $this->extension->getAlias());
    }

    public function testLoadRegistersServices(): void
    {
        $this->extension->load([], $this->container);
        
        // 验证工厂服务被注册
        $this->assertTrue($this->container->hasDefinition('doctrine_dedicated_connection.factory'));
        $this->assertTrue($this->container->hasAlias(DedicatedConnectionFactory::class));
        
        // 验证别名不是公开的
        $alias = $this->container->getAlias(DedicatedConnectionFactory::class);
        $this->assertFalse($alias->isPublic());
    }

    public function testLoadRegistersAttributeAutoconfiguration(): void
    {
        $this->extension->load([], $this->container);
        
        // 创建一个带有属性的测试服务
        $definition = $this->container->registerForAutoconfiguration(TestServiceWithAttribute::class);
        
        // 验证属性自动配置被注册
        $autoconfiguredAttributes = $this->container->getAutoconfiguredAttributes();
        $this->assertArrayHasKey(WithDedicatedConnection::class, $autoconfiguredAttributes);
    }

    public function testLoadWithMultipleConfigs(): void
    {
        $configs = [
            ['key1' => 'value1'],
            ['key2' => 'value2'],
        ];
        
        // 不应该抛出异常
        $this->extension->load($configs, $this->container);
        
        $this->assertTrue(true); // 成功加载
    }
}

/**
 * @internal
 */
#[WithDedicatedConnection(channel: 'test')]
class TestServiceWithAttribute
{
}