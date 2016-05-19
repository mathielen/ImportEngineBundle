<?php

namespace Mathielen\ImportEngineBundle\Tests\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class CompareTest extends AbstractTest
{
    private function getXmlDefinitions($filename)
    {
        $this->setUp();
        $container = $this->container;
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/Fixtures/Xml/'));
        $loader->load("$filename.xml");

        $container->loadFromExtension('mathielen_import_engine');
        $container->compile();

        return $container->getDefinitions();
    }

    private function getYamlDefinitions($filename)
    {
        $this->setUp();
        $container = $this->container;
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/Fixtures/Yaml/'));
        $loader->load("$filename.yml");

        $container->loadFromExtension('mathielen_import_engine');
        $container->compile();

        return $container->getDefinitions();
    }

    public function testFullXmlAndYamlSame()
    {
        $this->assertEquals($this->getYamlDefinitions('full'), $this->getXmlDefinitions('full'));
        $this->assertEquals($this->getYamlDefinitions('medium'), $this->getXmlDefinitions('medium'));
        $this->assertEquals($this->getYamlDefinitions('minimum'), $this->getXmlDefinitions('minimum'));
    }
}

class MyImportedRow
{
}
