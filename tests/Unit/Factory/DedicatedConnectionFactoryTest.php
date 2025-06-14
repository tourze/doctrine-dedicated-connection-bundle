<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\Tests\Unit\Factory;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tourze\DoctrineDedicatedConnectionBundle\Factory\DedicatedConnectionFactory;

class DedicatedConnectionFactoryTest extends TestCase
{
    private Connection $defaultConnection;
    private DedicatedConnectionFactory $factory;

    public function testCreateConnectionWithDefaultParams(): void
    {
        // Mock environment variables
        $_ENV['ORDER_DB_HOST'] = 'order.example.com';
        $_ENV['ORDER_DB_PORT'] = '3307';
        $_ENV['ORDER_DB_USER'] = 'order_user';
        $_ENV['ORDER_DB_PASSWORD'] = 'order_pass';

        $connection = $this->factory->createConnection('order');

        $this->assertInstanceOf(Connection::class, $connection);

        // Clean up
        unset($_ENV['ORDER_DB_HOST'], $_ENV['ORDER_DB_PORT'], $_ENV['ORDER_DB_USER'], $_ENV['ORDER_DB_PASSWORD']);
    }

    public function testCreateConnectionWithDatabaseSuffix(): void
    {
        // Don't set ANALYTICS_DB_NAME, so it should use default + suffix
        $connection = $this->factory->createConnection('analytics');

        $this->assertInstanceOf(Connection::class, $connection);

        // The factory should append '_analytics' to the default db name
        $params = $connection->getParams();
        $this->assertEquals('test_db_analytics', $params['dbname']);
    }

    public function testConnectionCaching(): void
    {
        $connection1 = $this->factory->createConnection('cache_test');
        $connection2 = $this->factory->createConnection('cache_test');

        $this->assertSame($connection1, $connection2);
    }

    public function testGetConnections(): void
    {
        $this->factory->createConnection('conn1');
        $this->factory->createConnection('conn2');

        $connections = $this->factory->getConnections();

        $this->assertCount(2, $connections);
        $this->assertArrayHasKey('conn1', $connections);
        $this->assertArrayHasKey('conn2', $connections);
    }

    public function testCloseAll(): void
    {
        $connection1 = $this->factory->createConnection('close1');
        $connection2 = $this->factory->createConnection('close2');

        $this->factory->closeAll();

        $this->assertCount(0, $this->factory->getConnections());
    }

    protected function setUp(): void
    {
        $this->defaultConnection = $this->createMock(Connection::class);
        $this->defaultConnection->method('getParams')->willReturn([
            'driver' => 'pdo_mysql',
            'host' => 'localhost',
            'port' => 3306,
            'dbname' => 'test_db',
            'user' => 'root',
            'password' => 'password',
            'charset' => 'utf8mb4',
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $this->factory = new DedicatedConnectionFactory($this->defaultConnection, $logger);
    }
}