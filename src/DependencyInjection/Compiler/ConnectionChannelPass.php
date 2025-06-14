<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\DependencyInjection\Compiler;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * 处理 doctrine.dedicated_connection 标签的编译器传递
 * 这个 Pass 必须在 AutowirePass 之后运行，以便正确处理参数
 */
class ConnectionChannelPass implements CompilerPassInterface
{
    use ConnectionCreationTrait;
    
    public function process(ContainerBuilder $container): void
    {
        $taggedServices = $container->findTaggedServiceIds('doctrine.dedicated_connection');
        
        if (empty($taggedServices)) {
            return;
        }
        
        foreach ($taggedServices as $id => $tags) {
            $definition = $container->getDefinition($id);
            
            foreach ($tags as $attributes) {
                $channel = $attributes['channel'] ?? null;
                if (!$channel) {
                    throw new \InvalidArgumentException(sprintf(
                        'Service "%s" has a "doctrine.dedicated_connection" tag without a "channel" attribute.',
                        $id
                    ));
                }

                // 确保连接服务存在
                $this->ensureConnectionService($container, $channel);
                
                // 获取连接服务 ID
                $connectionServiceId = sprintf('doctrine.dbal.%s_connection', $channel);
                
                // 处理服务的 Connection 参数
                $this->processConnectionArgument($container, $definition, $connectionServiceId);
            }
        }
    }
    
    /**
     * 处理服务的 Connection 参数
     */
    private function processConnectionArgument(ContainerBuilder $container, Definition $definition, string $connectionServiceId): void
    {
        $class = $definition->getClass();
        if (!$class || !$container->getReflectionClass($class, false)) {
            return;
        }
        
        try {
            $reflection = $container->getReflectionClass($class);
            $constructor = $reflection->getConstructor();
            
            if (!$constructor) {
                return;
            }
            
            // 查找 Connection 类型的参数
            foreach ($constructor->getParameters() as $index => $parameter) {
                $type = $parameter->getType();
                
                if (!$type instanceof \ReflectionNamedType) {
                    continue;
                }
                
                if ($type->getName() === Connection::class || $type->getName() === 'Doctrine\DBAL\Connection') {
                    // 直接设置参数，不管是否 autowired
                    $definition->setArgument($index, new Reference($connectionServiceId));
                    
                    break;
                }
            }
        } catch (\ReflectionException $e) {
            // 忽略反射错误
        }
    }
}
