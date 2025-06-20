<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\Tests\Unit\Factory;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tourze\DoctrineDedicatedConnectionBundle\Factory\DedicatedConnectionFactory;
use Tourze\Symfony\RuntimeContextBundle\Service\ContextServiceInterface;

class DedicatedConnectionFactoryTest extends TestCase
{
    private Connection $defaultConnection;
    private ContextServiceInterface $contextService;
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
        // Make sure no environment variable is set for this channel
        unset($_ENV['ANALYTICS_DB_NAME']);
        
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

    public function testCoroutineContextSupport(): void
    {
        // Mock a coroutine-supporting context service
        $coroutineContext = $this->createMock(ContextServiceInterface::class);
        $coroutineContext->method('supportCoroutine')->willReturn(true);
        $coroutineContext->method('getId')->willReturn('coroutine-123');
        
        $factory = new DedicatedConnectionFactory(
            $this->defaultConnection,
            $coroutineContext,
            $this->createMock(LoggerInterface::class)
        );

        $connection1 = $factory->createConnection('test');
        $connection2 = $factory->createConnection('test');

        // Same channel in same context should return same connection
        $this->assertSame($connection1, $connection2);

        $connections = $factory->getConnections();
        $this->assertCount(1, $connections);
        $this->assertArrayHasKey('coroutine-123:test', $connections);
    }

    public function testMultipleCoroutineContexts(): void
    {
        // First context
        $context1 = $this->createMock(ContextServiceInterface::class);
        $context1->method('supportCoroutine')->willReturn(true);
        $context1->method('getId')->willReturn('context-1');
        
        $factory1 = new DedicatedConnectionFactory(
            $this->defaultConnection,
            $context1,
            $this->createMock(LoggerInterface::class)
        );

        // Second context
        $context2 = $this->createMock(ContextServiceInterface::class);
        $context2->method('supportCoroutine')->willReturn(true);
        $context2->method('getId')->willReturn('context-2');
        
        $factory2 = new DedicatedConnectionFactory(
            $this->defaultConnection,
            $context2,
            $this->createMock(LoggerInterface::class)
        );

        $conn1 = $factory1->createConnection('test');
        $conn2 = $factory2->createConnection('test');

        // Different contexts should have different connections even with same channel
        $this->assertNotSame($conn1, $conn2);
    }

    public function testCloseAllWithContext(): void
    {
        $context = $this->createMock(ContextServiceInterface::class);
        $context->method('supportCoroutine')->willReturn(true);
        $context->method('getId')->willReturn('test-context');
        
        $factory = new DedicatedConnectionFactory(
            $this->defaultConnection,
            $context,
            $this->createMock(LoggerInterface::class)
        );

        $factory->createConnection('conn1');
        $factory->createConnection('conn2');

        $this->assertCount(2, $factory->getConnections());

        $factory->closeAll('test-context');

        $this->assertCount(0, $factory->getConnections());
    }

    public function testCloseCurrentContext(): void
    {
        $context = $this->createMock(ContextServiceInterface::class);
        $context->method('supportCoroutine')->willReturn(true);
        $context->method('getId')->willReturn('current-context');
        
        $factory = new DedicatedConnectionFactory(
            $this->defaultConnection,
            $context,
            $this->createMock(LoggerInterface::class)
        );

        $factory->createConnection('conn1');
        $factory->createConnection('conn2');

        $this->assertCount(2, $factory->getConnections());

        $factory->closeCurrentContext();

        $this->assertCount(0, $factory->getConnections());
    }

    public function testDeferredConnectionCleanup(): void
    {
        $deferredCallbacks = [];
        
        $context = $this->createMock(ContextServiceInterface::class);
        $context->method('supportCoroutine')->willReturn(true);
        $context->method('getId')->willReturn('defer-context');
        $context->method('defer')->willReturnCallback(function (callable $callback) use (&$deferredCallbacks) {
            $deferredCallbacks[] = $callback;
        });
        
        $factory = new DedicatedConnectionFactory(
            $this->defaultConnection,
            $context,
            $this->createMock(LoggerInterface::class)
        );

        $factory->createConnection('test');

        // Should have registered a deferred callback
        $this->assertCount(1, $deferredCallbacks);

        // Execute the deferred callback
        $deferredCallbacks[0]();

        // Connection should be closed
        $this->assertCount(0, $factory->getConnections());
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

        // Mock a default non-coroutine context service
        $this->contextService = $this->createMock(ContextServiceInterface::class);
        $this->contextService->method('supportCoroutine')->willReturn(false);
        $this->contextService->method('getId')->willReturn('default-context');

        $logger = $this->createMock(LoggerInterface::class);
        $this->factory = new DedicatedConnectionFactory(
            $this->defaultConnection,
            $this->contextService,
            $logger
        );
    }
}