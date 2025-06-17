<?php

namespace Tourze\DoctrineDedicatedConnectionBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Tourze\DoctrineDedicatedConnectionBundle\Attribute\WithDedicatedConnection;
use Tourze\DoctrineDedicatedConnectionBundle\Factory\DedicatedConnectionFactory;

class DoctrineDedicatedConnectionExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yaml');
        
        // Ensure the factory is available for compiler passes
        $container->setAlias(DedicatedConnectionFactory::class, 'doctrine_dedicated_connection.factory')
            ->setPublic(false);
        
        // Register attribute autoconfiguration
        $this->registerAttributeAutoconfiguration($container);
    }
    
    private function registerAttributeAutoconfiguration(ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(
            WithDedicatedConnection::class,
            static function (ChildDefinition $definition, WithDedicatedConnection $attribute): void {
                $definition->addTag('doctrine.dedicated_connection', [
                    'channel' => $attribute->channel,
                ]);
            }
        );
    }

    public function getAlias(): string
    {
        return 'doctrine_dedicated_connection';
    }
}
