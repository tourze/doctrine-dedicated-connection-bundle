<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\Factory;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * 创建或获取专用的数据库连接
     */
    public function createConnection(string $channel): Connection
    {
        if (isset($this->connections[$channel])) {
            return $this->connections[$channel];
        }

        $this->logger->debug('Creating dedicated connection for channel: {channel}', ['channel' => $channel]);

        // 获取默认连接参数
        $defaultParams = $this->defaultConnection->getParams();
        
        // 构建专用连接参数（完全基于环境变量）
        $params = $this->buildConnectionParams($channel, $defaultParams);
        
        // 创建连接
        $connection = DriverManager::getConnection($params);
        
        $this->connections[$channel] = $connection;
        
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
     * 获取所有已创建的连接
     */
    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * 关闭所有连接
     */
    public function closeAll(): void
    {
        foreach ($this->connections as $connection) {
            $connection->close();
        }
        
        $this->connections = [];
    }
}