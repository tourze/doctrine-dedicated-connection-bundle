<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\DoctrineDedicatedConnectionBundle\Exception\RuntimeException;

class RuntimeExceptionTest extends TestCase
{
    public function testExceptionExtendsPHPRuntimeException(): void
    {
        $exception = new RuntimeException('Test message');
        
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Runtime error occurred');
        
        throw new RuntimeException('Runtime error occurred');
    }

    public function testExceptionWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new RuntimeException('Test message', 456, $previous);
        
        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(456, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}