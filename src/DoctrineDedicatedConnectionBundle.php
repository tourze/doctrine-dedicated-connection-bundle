<?php

namespace Tourze\DoctrineDedicatedConnectionBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\DoctrineDedicatedConnectionBundle\DependencyInjection\Compiler\ConnectionChannelPass;
use Tourze\DoctrineDedicatedConnectionBundle\DependencyInjection\Compiler\DedicatedConnectionCompilerPass;
use Tourze\DoctrineDedicatedConnectionBundle\DependencyInjection\DoctrineDedicatedConnectionExtension;
use Tourze\Symfony\RuntimeContextBundle\RuntimeContextBundle;

class DoctrineDedicatedConnectionBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            DoctrineBundle::class => ['all' => true],
            RuntimeContextBundle::class => ['all' => true],
        ];
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // 注册编译器传递
        // ConnectionChannelPass 必须在 AutowirePass 之后运行
        // AutowirePass 在 TYPE_BEFORE_OPTIMIZATION 阶段运行，所以我们在 TYPE_BEFORE_REMOVING 阶段运行
        $container->addCompilerPass(new ConnectionChannelPass(), PassConfig::TYPE_BEFORE_REMOVING, 0);

        // DedicatedConnectionCompilerPass 处理手动标记的服务
        $container->addCompilerPass(new DedicatedConnectionCompilerPass(), PassConfig::TYPE_BEFORE_REMOVING, 0);
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new DoctrineDedicatedConnectionExtension();
        }

        return $this->extension;
    }
}
