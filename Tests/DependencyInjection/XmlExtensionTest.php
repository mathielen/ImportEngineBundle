<?php

namespace Mathielen\ImportEngineBundle\Tests\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class XmlExtensionTest extends AbstractExtensionTest
{
    protected function loadConfiguration(ContainerBuilder $container, $resource)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/Fixtures/Xml/'));
        $loader->load($resource.'.xml');
    }
}
