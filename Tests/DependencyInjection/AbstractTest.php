<?php

namespace Mathielen\ImportEngineBundle\Tests\DependencyInjection;

use Mathielen\ImportEngineBundle\DependencyInjection\MathielenImportEngineExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

abstract class AbstractTest extends \PHPUnit_Framework_TestCase
{
    protected $extension;

    /**
     * @var ContainerBuilder
     */
    protected $container;

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
        $this->container->set('some.converter.serviceid', new MyDummyService());
        $this->container->set('some.other.converter.serviceid', new MyDummyService());
        $this->container->set('email', new Email());
        $this->container->set('url', new Url());
        $this->container->set('notempty', new NotBlank());
    }
}

class MyImportService
{
}

class MyDummyService
{
}
