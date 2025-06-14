<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\Tests\Unit\Attribute;

use PHPUnit\Framework\TestCase;
use Tourze\DoctrineDedicatedConnectionBundle\Attribute\WithDedicatedConnection;

class WithDedicatedConnectionTest extends TestCase
{
    public function testAttributeConstruction(): void
    {
        $attribute = new WithDedicatedConnection('test_channel');
        
        $this->assertEquals('test_channel', $attribute->channel);
    }

    public function testAttributeCanBeAppliedToClass(): void
    {
        $reflectionClass = new \ReflectionClass(TestClassWithAttribute::class);
        $attributes = $reflectionClass->getAttributes(WithDedicatedConnection::class);
        
        $this->assertCount(1, $attributes);
        
        $attribute = $attributes[0]->newInstance();
        $this->assertInstanceOf(WithDedicatedConnection::class, $attribute);
        $this->assertEquals('test', $attribute->channel);
    }
}

#[WithDedicatedConnection('test')]
class TestClassWithAttribute
{
}