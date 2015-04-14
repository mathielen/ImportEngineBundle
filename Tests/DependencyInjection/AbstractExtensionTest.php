<?php
namespace Mathielen\ImportEngineBundle\Tests\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;

abstract class AbstractExtensionTest extends AbstractTest
{

    abstract protected function loadConfiguration(ContainerBuilder $container, $resource);

    private function getContainer($resource = null)
    {
        if ($resource) {
            $this->loadConfiguration($this->container, $resource);
        }

        $this->container->loadFromExtension($this->extension->getAlias());
        $this->container->compile();

        return $this->container;
    }

    public function testWithoutConfiguration()
    {
        $container = $this->getContainer();
        $this->assertFalse($container->has('mathielen_importengine.import.storagelocator'));
        $this->assertFalse($container->has('mathielen_importengine.import.builder'));
    }

    public function testFullConfiguration()
    {
        $container = $this->getContainer('full');
        $this->assertTrue($container->has('mathielen_importengine.import.storagelocator'));
        $this->assertTrue($container->has('mathielen_importengine.import.builder'));
    }

    public function testMediumConfiguration()
    {
        $container = $this->getContainer('medium');
        $this->assertTrue($container->has('mathielen_importengine.import.storagelocator'));
        $this->assertTrue($container->has('mathielen_importengine.import.builder'));
    }

    public function testMinimumConfiguration()
    {
        $container = $this->getContainer('minimum');
        $this->assertTrue($container->has('mathielen_importengine.import.storagelocator'));
        $this->assertTrue($container->has('mathielen_importengine.import.builder'));
    }

    public function testStorageProvidersAreProperlyRegisteredByTheirName()
    {
        $container = $this->getContainer('full');

        $storageLocatorDef = $container->findDefinition('mathielen_importengine.import.storagelocator');
        $methodCalls       = $storageLocatorDef->getMethodCalls();

        $registeredStorageProviderIds = [];
        foreach ($methodCalls as $methodCall) {
            $arguments = $methodCall[1];

            $registeredStorageProviderIds[] = $arguments[0];
        }

        $this->assertEquals(['upload', 'localdir', 'localfile', 'doctrine', 'services'], $registeredStorageProviderIds);
    }
}
