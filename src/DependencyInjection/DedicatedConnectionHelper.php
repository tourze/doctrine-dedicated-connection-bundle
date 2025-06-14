<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\DependencyInjection;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * 辅助类，用于在自定义 CompilerPass 中创建专用数据库连接
 * 
 * 使用示例：
 * ```php
 * use Tourze\DoctrineDedicatedConnectionBundle\DependencyInjection\DedicatedConnectionHelper;
 * 
 * class MyCompilerPass implements CompilerPassInterface
 * {
 *     public function process(ContainerBuilder $container): void
 *     {
 *         // 创建一个专用连接
 *         $connectionId = DedicatedConnectionHelper::createConnection($container, 'analytics');
 *         
 *         // 在服务定义中使用这个连接
 *         $container->getDefinition('my.analytics.service')
 *             ->addArgument(new Reference($connectionId));
 *     }
 * }
 * ```
 */
class DedicatedConnectionHelper
{
    /**
     * 创建专用数据库连接
     * 
     * @param ContainerBuilder $container
     * @param string $channel 连接通道名称
     * @return string 返回创建的连接服务 ID
     * 
     * @throws \RuntimeException 如果 bundle 未正确注册
     */
    public static function createConnection(ContainerBuilder $container, string $channel): string
    {
        $connectionId = sprintf('doctrine.dbal.%s_connection', $channel);
        
        // 如果连接已存在，直接返回 ID
        if ($container->hasDefinition($connectionId) || $container->hasAlias($connectionId)) {
            return $connectionId;
        }

        // 确保工厂服务存在
        if (!$container->hasDefinition('doctrine_dedicated_connection.factory')) {
            throw new \RuntimeException(
                'The doctrine_dedicated_connection.factory service is not defined. ' .
                'Make sure DoctrineDedicatedConnectionBundle is registered before your CompilerPass.'
            );
        }

        $factory = new Reference('doctrine_dedicated_connection.factory');
        
        // 创建连接定义
        $connectionDef = new Definition(Connection::class);
        $connectionDef->setFactory([$factory, 'createConnection']);
        $connectionDef->setArguments([$channel]);
        $connectionDef->setPublic(false);
        $connectionDef->addTag('doctrine.connection');
        
        $container->setDefinition($connectionId, $connectionDef);
        
        return $connectionId;
    }

    /**
     * 检查指定的连接是否已存在
     * 
     * @param ContainerBuilder $container
     * @param string $channel
     * @return bool
     */
    public static function hasConnection(ContainerBuilder $container, string $channel): bool
    {
        $connectionId = sprintf('doctrine.dbal.%s_connection', $channel);
        return $container->hasDefinition($connectionId) || $container->hasAlias($connectionId);
    }

    /**
     * 获取连接的服务 ID
     * 
     * @param string $channel
     * @return string
     */
    public static function getConnectionId(string $channel): string
    {
        return sprintf('doctrine.dbal.%s_connection', $channel);
    }
}