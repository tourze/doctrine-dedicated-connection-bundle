# Doctrine Dedicated Connection Bundle

这个 Bundle 提供了一种简单的方式来为 Symfony 服务创建专用的数据库连接，类似于 MonologBundle 的 `WithMonologChannel` 功能。专注于连接管理，不涉及 EntityManager。

## 功能特性

- 🔌 **自动创建专用数据库连接** - 通过注解自动创建独立的数据库连接
- 🏷️ **注解支持** - 使用 `#[WithDedicatedConnection]` 注解标记需要专用连接的服务
- 🔧 **环境变量配置** - 支持通过环境变量覆盖每个连接的配置
- 🔄 **连接池管理** - 自动管理和重置连接
- 🚫 **零配置** - 无需 YAML 配置文件，全部通过注解和环境变量管理

## 安装

```bash
composer require tourze/doctrine-dedicated-connection-bundle
```

## 使用方法

### 使用注解方式

使用 `WithDedicatedConnection` 注解标记服务：

```php
<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Tourze\DoctrineDedicatedConnectionBundle\Attribute\WithDedicatedConnection;

#[WithDedicatedConnection('order')]
class OrderService
{
    public function __construct(
        private readonly Connection $connection
    ) {
        // 这里会自动注入 order 专用的 Connection
    }
    
    public function createOrder(): void
    {
        // 使用专用的数据库连接处理订单
        $this->connection->insert('orders', [...]);
    }
}
```

### 使用标签方式

```yaml
services:
  App\Service\OrderService:
    tags:
      - { name: doctrine.dedicated_connection, channel: order }
```

## 环境变量配置

每个连接支持通过环境变量覆盖配置：

```bash
# 订单数据库配置
ORDER_DB_HOST=localhost
ORDER_DB_PORT=3306
ORDER_DB_NAME=my_app_orders
ORDER_DB_USER=order_user
ORDER_DB_PASSWORD=secret_password
ORDER_DB_DRIVER=pdo_mysql
ORDER_DB_CHARSET=utf8mb4

# 分析数据库配置
ANALYTICS_DB_HOST=analytics.example.com
ANALYTICS_DB_NAME=analytics_db
```

如果没有设置 `{CHANNEL}_DB_NAME`，将使用默认数据库名称加上 `_channel` 后缀。

## 注解参数

`WithDedicatedConnection` 注解只有一个参数：

- **channel** (必需): 连接通道名称

```php
#[WithDedicatedConnection('archive')]
class ArchiveService
{
    public function __construct(
        private readonly Connection $connection
    ) {
        // 使用 archive 数据库连接
    }
}
```

所有的数据库配置都通过环境变量管理，确保配置的一致性和可维护性。

## 高级用法

### 在自定义 CompilerPass 中创建连接

如果你需要在自己的 CompilerPass 中创建专用连接，可以使用 `DedicatedConnectionHelper`：

```php
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Tourze\DoctrineDedicatedConnectionBundle\DependencyInjection\DedicatedConnectionHelper;

class MyCustomCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // 创建一个专用连接
        $connectionId = DedicatedConnectionHelper::createConnection($container, 'analytics');
        
        // 在服务定义中使用这个连接
        $container->getDefinition('my.analytics.service')
            ->addArgument(new Reference($connectionId));
            
        // 或者检查连接是否已存在
        if (!DedicatedConnectionHelper::hasConnection($container, 'reporting')) {
            $reportingConnectionId = DedicatedConnectionHelper::createConnection($container, 'reporting');
            // 使用这个连接...
        }
    }
}
```

### 直接使用工厂服务

```php
use Tourze\DoctrineDedicatedConnectionBundle\Factory\DedicatedConnectionFactory;

class MyService
{
    public function __construct(
        private readonly DedicatedConnectionFactory $connectionFactory
    ) {}
    
    public function doSomething(): void
    {
        // 手动创建连接
        $connection = $this->connectionFactory->createConnection('custom_channel');
    }
}
```

### 获取特定的连接

创建的服务遵循以下命名约定：

- 连接: `doctrine.dbal.{channel}_connection`

```yaml
services:
  App\Service\ReportService:
    arguments:
      $orderConnection: '@doctrine.dbal.order_connection'
      $analyticsConnection: '@doctrine.dbal.analytics_connection'
```

## 最佳实践

1. **使用有意义的通道名称**：使用描述性的名称如 `order`、`analytics`、`archive` 等
2. **按业务领域分离数据**：每个微服务或业务领域使用独立的数据库
3. **配置环境变量**：在生产环境中使用环境变量配置敏感信息
4. **连接重用**：如果多个服务需要同一个专用连接，使用相同的 channel 名称

## 故障排除


### 连接无法建立

确保：
- 环境变量配置正确
- 数据库服务器可访问
- 清除缓存：`php bin/console cache:clear`

## 许可证

MIT
