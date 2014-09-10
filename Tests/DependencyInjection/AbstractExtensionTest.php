<?php
namespace Mathielen\ImportEngineBundle\Tests\DependencyInjection;

use Mathielen\ImportEngineBundle\DependencyInjection\MathielenImportEngineExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

abstract class AbstractExtensionTest extends \PHPUnit_Framework_TestCase
{
    private $extension;

    /**
     * @var ContainerBuilder
     */
    private $container;

    protected function setUp()
    {
        $this->extension = new MathielenImportEngineExtension();

        $this->container = new ContainerBuilder();
        $this->container->registerExtension($this->extension);
        $this->container->register('event_dispatcher', $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface'));
    }

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

    private function registerFullConfigurationDependencies()
    {
        $this->container->register('import_service', new MyImportService()); //target service
        $this->container->register('jms_serializer', $this->getMock('JMS\Serializer\SerializerInterface'));
        $this->container->register('validator', $this->getMock('Symfony\Component\Validator\ValidatorInterface'));
        $this->container->register('doctrine.orm.entity_manager', $this->getMock('Doctrine\ORM\EntityManagerInterface'));
    }

    public function testWithoutConfiguration()
    {
        $container = $this->getContainer();
        $this->assertFalse($container->has('mathielen_importengine.import.storagelocator'));
        $this->assertFalse($container->has('mathielen_importengine.import.builder'));
    }

    public function testFullConfiguration()
    {
        $this->registerFullConfigurationDependencies();

        $container = $this->getContainer('full');
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
        $this->registerFullConfigurationDependencies();

        $container = $this->getContainer('full');

        $storageLocatorDef = $container->findDefinition('mathielen_importengine.import.storagelocator');
        $methodCalls       = $storageLocatorDef->getMethodCalls();

        $registeredStorageProviderIds = [];
        foreach ($methodCalls as $methodCall) {
            $arguments = $methodCall[1];

            $registeredStorageProviderIds[] = $arguments[0];
        }

        $this->assertEquals(['upload', 'local', 'doctrine'], $registeredStorageProviderIds);
    }
}

class MyImportService
{
}
