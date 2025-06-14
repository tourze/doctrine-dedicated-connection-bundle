<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\Tests\Fixtures;

use Doctrine\DBAL\Connection;

class TestServiceWithTag
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function getDatabaseName(): ?string
    {
        $params = $this->connection->getParams();
        return $params['dbname'] ?? null;
    }
}