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
        $this->container->set('event_dispatcher', $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface'));
        $this->container->set('logger', $this->getMock('Psr\Log\LoggerInterface'));
        $this->container->set('import_service', new MyImportService()); //target service
        $this->container->set('jms_serializer', $this->getMock('JMS\Serializer\SerializerInterface'));
        $this->container->set('validator', $this->getMock('Symfony\Component\Validator\ValidatorInterface'));
        $this->container->set('doctrine.orm.entity_manager', $this->getMock('Doctrine\ORM\EntityManagerInterface'));
        $this->container->set('logger', $this->getMock('Psr\Log\LoggerInterface'));
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

class MyImportService
{
}
