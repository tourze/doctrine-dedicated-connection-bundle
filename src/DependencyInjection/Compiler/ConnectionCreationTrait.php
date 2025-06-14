<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\DependencyInjection\Compiler;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * 共享的连接创建逻辑
 */
trait ConnectionCreationTrait
{
    /**
     * 确保连接服务存在
     */
    protected function ensureConnectionService(ContainerBuilder $container, string $channel, array $attributes = []): void
    {
        $connectionId = sprintf('doctrine.dbal.%s_connection', $channel);
        
        // 如果连接已存在，直接返回
        if ($container->hasDefinition($connectionId) || $container->hasAlias($connectionId)) {
            return;
        }

        $factory = new Reference('doctrine_dedicated_connection.factory');
        
        // 创建连接定义
        $connectionDef = new Definition(Connection::class);
        $connectionDef->setFactory([$factory, 'createConnection']);
        $connectionDef->setArguments([$channel]);
        $connectionDef->setPublic(false);
        $connectionDef->addTag('doctrine.connection');
        
        $container->setDefinition($connectionId, $connectionDef);
    }
}
