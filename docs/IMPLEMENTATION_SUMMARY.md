# DoctrineDedicatedConnectionBundle 实现总结

## 概述

本 Bundle 实现了类似于 MonologBundle 的 `WithMonologChannel` 功能，允许通过 PHP 8 属性（Attributes）为服务自动注入专用的数据库连接。

## 核心功能

### 1. 属性自动配置

通过 `WithDedicatedConnection` 属性，可以为任何服务指定专用的数据库连接：

```php
#[WithDedicatedConnection('orders')]
class OrderService
{
    public function __construct(private Connection $connection)
    {
    }
}
```

### 2. 环境变量配置

连接参数通过环境变量配置，格式为 `{CHANNEL}_DB_{PARAM}`：

```bash
ORDERS_DB_HOST=orders.database.com
ORDERS_DB_NAME=orders_db
ORDERS_DB_USER=orders_user
ORDERS_DB_PASSWORD=secret
```

### 3. 标签支持

除了属性，还支持通过服务标签配置：

```yaml
services:
    my.service:
        class: App\Service\MyService
        tags:
            - { name: doctrine.dedicated_connection, channel: analytics }
```

## 技术实现要点

### 1. 属性注册时机

属性配置器在 Extension 的 `load()` 方法中注册，确保在容器编译早期就可用：

```php
// DoctrineDedicatedConnectionExtension::load()
$container->registerAttributeForAutoconfiguration(
    WithDedicatedConnection::class,
    static function (ChildDefinition $definition, WithDedicatedConnection $attribute): void {
        $definition->addTag('doctrine.dedicated_connection', ['channel' => $attribute->channel]);
    }
);
```

### 2. CompilerPass 执行顺序

经过深入研究 Symfony 的编译流程，发现了关键点：

- `ResolveBindingsPass` 在 `AutowirePass` **之前**执行
- 设置绑定时，如果服务还没有完整的参数定义，会抛出错误
- 解决方案：在 `TYPE_BEFORE_REMOVING` 阶段运行我们的 CompilerPass

```php
// DoctrineDedicatedConnectionBundle::build()
$container->addCompilerPass(new ConnectionChannelPass(), PassConfig::TYPE_BEFORE_REMOVING, 0);
```

### 3. 参数替换策略

不使用绑定机制，而是直接替换服务的构造函数参数：

```php
// ConnectionChannelPass::processConnectionArgument()
foreach ($constructor->getParameters() as $index => $parameter) {
    if ($type->getName() === Connection::class) {
        $definition->setArgument($index, new Reference($connectionServiceId));
        break;
    }
}
```

## 已知限制

1. **环境变量命名**：channel 名称会被转换为大写，可能导致混淆
2. **单连接限制**：每个服务只能注入一个专用连接
3. **类型限制**：只支持 `Doctrine\DBAL\Connection` 类型

## 测试状态

- ✅ 核心功能测试通过（21/29）
- ✅ 属性自动配置工作正常
- ✅ 标签服务处理正确
- ✅ 环境变量覆盖功能正常
- ❌ 部分边缘案例测试仍需修复

## 经验教训

1. **深入理解 Symfony 编译流程至关重要**
   - 必须了解各个 CompilerPass 的执行顺序
   - 理解 `ResolveBindingsPass` 的工作原理

2. **参考官方实现**
   - MonologBundle 的实现提供了很好的参考
   - 但不能完全照搬，需要根据具体情况调整

3. **测试驱动开发**
   - 完整的测试用例帮助快速定位问题
   - 集成测试特别重要

## 后续改进建议

1. **改进环境变量命名策略**
   - 考虑支持自定义环境变量前缀
   - 提供更灵活的配置选项

2. **支持多连接注入**
   - 允许一个服务注入多个不同的连接
   - 通过参数名称区分不同的连接

3. **添加配置验证**
   - 在编译时验证 channel 名称的有效性
   - 提供更好的错误信息

4. **性能优化**
   - 考虑缓存反射结果
   - 优化大型项目的编译性能