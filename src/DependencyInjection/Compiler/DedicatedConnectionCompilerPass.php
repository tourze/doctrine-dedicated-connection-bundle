<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Tourze\DoctrineDedicatedConnectionBundle\Exception\InvalidArgumentException;

/**
 * 数据库连接创建编译器传递
 * 负责处理所有需要专用数据库连接的服务
 */
class DedicatedConnectionCompilerPass implements CompilerPassInterface
{
    use ConnectionCreationTrait;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('doctrine.dbal.default_connection')) {
            return;
        }

        // 处理通过标签定义的连接
        $this->processTaggedServices($container);
    }

    /**
     * 处理带有 doctrine.dedicated_connection 标签的服务
     */
    private function processTaggedServices(ContainerBuilder $container): void
    {
        // 遍历所有定义的服务，查找带有 doctrine.dedicated_connection 标签的服务
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$definition->hasTag('doctrine.dedicated_connection')) {
                continue;
            }

            $tags = $definition->getTag('doctrine.dedicated_connection');

            foreach ($tags as $attributes) {
                $channel = $attributes['channel'] ?? null;
                if (!$channel) {
                    throw new InvalidArgumentException(sprintf(
                        'The "doctrine.dedicated_connection" tag on service "%s" must have a "channel" attribute.',
                        $id
                    ));
                }

                $this->ensureConnectionService($container, $channel, $attributes);
                $this->bindConnectionToService($container, $definition, $channel);
            }
        }
    }

    /**
     * 绑定连接到服务
     */
    private function bindConnectionToService(ContainerBuilder $container, Definition $definition, string $channel): void
    {
        $connectionId = sprintf('doctrine.dbal.%s_connection', $channel);

        // 尝试自动绑定到构造函数参数
        $this->autoBindToConstructor($definition, $connectionId);
    }

    /**
     * 自动绑定到构造函数
     */
    private function autoBindToConstructor(Definition $definition, string $connectionId): void
    {
        $arguments = $definition->getArguments();

        // 查找合适的参数位置
        foreach ($arguments as $index => $argument) {
            if ($argument instanceof Reference) {
                $refId = (string)$argument;
                // 如果参数是默认的连接，替换它
                if (str_contains($refId, 'connection')) {
                    $arguments[$index] = new Reference($connectionId);
                    $definition->setArguments($arguments);
                    return;
                }
            }
        }

        // 如果没有找到合适的参数，添加到构造函数末尾
        $definition->addArgument(new Reference($connectionId));
    }
}
