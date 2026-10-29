<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\DependencyInjection;

use JetroTools\ContaoBundle\JetroToolsContaoBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class JetroToolsContaoExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->setParameter('jetro_contao.version', JetroToolsContaoBundle::VERSION);

        (new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config')))->load('services.yaml');
    }
}
