<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\Attribute;

/**
 * 标记一个服务需要使用专用的数据库连接
 *
 * 使用示例：
 * ```php
 * #[WithDedicatedConnection('order')]
 * class OrderService
 * {
 *     public function __construct(
 *         private readonly Connection $connection
 *     ) {}
 * }
 * ```
 *
 * 该注解会自动创建专用的数据库连接并注入到服务中
 * 数据库配置通过环境变量管理，例如：ORDER_DB_HOST, ORDER_DB_NAME 等
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class WithDedicatedConnection
{
    /**
     * @param string $channel 连接通道名称，用于标识不同的数据库连接
     */
    public function __construct(
        public readonly string $channel
    ) {
    }
}
