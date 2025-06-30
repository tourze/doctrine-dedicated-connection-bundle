<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\DoctrineDedicatedConnectionBundle\Exception\InvalidArgumentException;

class InvalidArgumentExceptionTest extends TestCase
{
    public function testExceptionExtendsPHPInvalidArgumentException(): void
    {
        $exception = new InvalidArgumentException('Test message');
        
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid argument provided');
        
        throw new InvalidArgumentException('Invalid argument provided');
    }

    public function testExceptionWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new InvalidArgumentException('Test message', 123, $previous);
        
        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(123, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}