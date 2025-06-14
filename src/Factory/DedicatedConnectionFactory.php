<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\Factory;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tourze\Symfony\RuntimeContextBundle\Service\ContextServiceInterface;

/**
 * 专用数据库连接工厂
 * 负责创建和管理多个独立的数据库连接
 */
class DedicatedConnectionFactory
{
    private array $connections = [];
    private LoggerInterface $logger;

    public function __construct(
        private readonly Connection $defaultConnection,
        private readonly ContextServiceInterface $contextService,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * 创建或获取专用的数据库连接
     * 在协程环境中，每个协程上下文都会有独立的连接池
     */
    public function createConnection(string $channel): Connection
    {
        // 获取上下文相关的连接键
        $contextKey = $this->getContextKey($channel);

        if (isset($this->connections[$contextKey])) {
            return $this->connections[$contextKey];
        }

        $this->logger->debug('Creating dedicated connection for channel: {channel} in context: {context}', [
            'channel' => $channel,
            'context' => $this->contextService->getId()
        ]);

        // 获取默认连接参数
        $defaultParams = $this->defaultConnection->getParams();
        
        // 构建专用连接参数（完全基于环境变量）
        $params = $this->buildConnectionParams($channel, $defaultParams);
        
        // 创建连接
        $connection = DriverManager::getConnection($params);
        
        $this->connections[$contextKey] = $connection;
        
        // 在协程环境中，注册连接清理回调
        if ($this->contextService->supportCoroutine()) {
            $this->contextService->defer(function () use ($contextKey) {
                $this->closeConnection($contextKey);
            });
        }
        
        return $connection;
    }


    /**
     * 构建连接参数
     */
    private function buildConnectionParams(string $channel, array $defaultParams): array
    {
        $params = $defaultParams;
        $envPrefix = strtoupper($channel);

        // 支持通过环境变量覆盖
        $envMappings = [
            'host' => 'DB_HOST',
            'port' => 'DB_PORT',
            'dbname' => 'DB_NAME',
            'user' => 'DB_USER',
            'password' => 'DB_PASSWORD',
            'driver' => 'DB_DRIVER',
            'charset' => 'DB_CHARSET',
            'server_version' => 'DB_SERVER_VERSION',
        ];

        foreach ($envMappings as $param => $envSuffix) {
            $envVar = "{$envPrefix}_{$envSuffix}";
            if (isset($_ENV[$envVar])) {
                $params[$param] = $_ENV[$envVar];
                if ($param === 'port') {
                    $params[$param] = (int) $params[$param];
                }
            }
        }

        // 如果没有指定数据库名，使用默认数据库名 + channel 后缀
        if (!isset($_ENV["{$envPrefix}_DB_NAME"]) && isset($params['dbname'])) {
            $params['dbname'] = $params['dbname'] . '_' . $channel;
        }

        return $params;
    }

    /**
     * 获取上下文相关的连接键
     */
    private function getContextKey(string $channel): string
    {
        if ($this->contextService->supportCoroutine()) {
            return $this->contextService->getId() . ':' . $channel;
        }
        
        return $channel;
    }

    /**
     * 关闭指定的连接
     */
    private function closeConnection(string $contextKey): void
    {
        if (isset($this->connections[$contextKey])) {
            $this->logger->debug('Closing dedicated connection: {contextKey}', ['contextKey' => $contextKey]);
            $this->connections[$contextKey]->close();
            unset($this->connections[$contextKey]);
        }
    }

    /**
     * 获取所有已创建的连接
     */
    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * 关闭所有连接，或者只关闭当前上下文的连接
     */
    public function closeAll(?string $contextId = null): void
    {
        if ($contextId === null) {
            // 关闭所有连接
            foreach ($this->connections as $connection) {
                $connection->close();
            }
            $this->connections = [];
        } else {
            // 只关闭指定上下文的连接
            $prefix = $contextId . ':';
            $toClose = [];
            
            foreach (array_keys($this->connections) as $key) {
                if (str_starts_with($key, $prefix)) {
                    $toClose[] = $key;
                }
            }
            
            foreach ($toClose as $key) {
                $this->closeConnection($key);
            }
        }
    }

    /**
     * 关闭当前上下文的所有连接
     */
    public function closeCurrentContext(): void
    {
        if ($this->contextService->supportCoroutine()) {
            $this->closeAll($this->contextService->getId());
        } else {
            $this->closeAll();
        }
    }
}
