<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\DoctrineDedicatedConnectionBundle\DependencyInjection\Compiler\ConnectionChannelPass;
use Tourze\DoctrineDedicatedConnectionBundle\DependencyInjection\Compiler\DedicatedConnectionCompilerPass;
use Tourze\DoctrineDedicatedConnectionBundle\DependencyInjection\DoctrineDedicatedConnectionExtension;
use Tourze\DoctrineDedicatedConnectionBundle\DoctrineDedicatedConnectionBundle;

class DoctrineDedicatedConnectionBundleTest extends TestCase
{
    private DoctrineDedicatedConnectionBundle $bundle;

    protected function setUp(): void
    {
        $this->bundle = new DoctrineDedicatedConnectionBundle();
    }

    public function testGetBundleDependencies(): void
    {
        $dependencies = DoctrineDedicatedConnectionBundle::getBundleDependencies();
        
        $this->assertArrayHasKey('Doctrine\Bundle\DoctrineBundle\DoctrineBundle', $dependencies);
        $this->assertArrayHasKey('Tourze\Symfony\RuntimeContextBundle\RuntimeContextBundle', $dependencies);
        $this->assertEquals(['all' => true], $dependencies['Doctrine\Bundle\DoctrineBundle\DoctrineBundle']);
        $this->assertEquals(['all' => true], $dependencies['Tourze\Symfony\RuntimeContextBundle\RuntimeContextBundle']);
    }

    public function testBuild(): void
    {
        $container = new ContainerBuilder();
        $passesBeforeBuild = count($container->getCompilerPassConfig()->getPasses());
        
        $this->bundle->build($container);
        
        $passesAfterBuild = count($container->getCompilerPassConfig()->getPasses());
        $this->assertEquals($passesBeforeBuild + 2, $passesAfterBuild);
        
        $passes = $container->getCompilerPassConfig()->getBeforeRemovingPasses();
        $hasConnectionChannelPass = false;
        $hasDedicatedConnectionPass = false;
        
        foreach ($passes as $pass) {
            if ($pass instanceof ConnectionChannelPass) {
                $hasConnectionChannelPass = true;
            }
            if ($pass instanceof DedicatedConnectionCompilerPass) {
                $hasDedicatedConnectionPass = true;
            }
        }
        
        $this->assertTrue($hasConnectionChannelPass, 'ConnectionChannelPass should be registered');
        $this->assertTrue($hasDedicatedConnectionPass, 'DedicatedConnectionCompilerPass should be registered');
    }

    public function testGetContainerExtension(): void
    {
        $extension = $this->bundle->getContainerExtension();
        
        $this->assertInstanceOf(DoctrineDedicatedConnectionExtension::class, $extension);
        
        // Test that the same instance is returned on subsequent calls
        $extension2 = $this->bundle->getContainerExtension();
        $this->assertSame($extension, $extension2);
    }
}