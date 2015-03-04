<?php
namespace Mathielen\ImportEngineBundle\Tests\Command;

use Mathielen\ImportEngine\ValueObject\ImportConfiguration;
use Mathielen\ImportEngine\ValueObject\ImportRun;
use Mathielen\ImportEngine\ValueObject\StorageSelection;
use Mathielen\ImportEngineBundle\Command\ImportCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ImportCommandTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var ImportCommand
     */
    private $command;

    /**
     * @var ContainerBuilder
     */
    private $container;

    protected function setUp()
    {
        $sl = $this->getMockBuilder('Mathielen\ImportEngine\Storage\StorageLocator')->disableOriginalConstructor()->getMock();
        $ib = $this->getMockBuilder('Mathielen\ImportEngine\Import\ImportBuilder')->disableOriginalConstructor()->getMock();

        $this->container = new ContainerBuilder();
        $this->container->set('event_dispatcher', $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface'));
        $this->container->set('mathielen_importengine.import.builder', $ib);
        $this->container->set('mathielen_importengine.import.storagelocator', $sl);
        $this->container->set('mathielen_importengine.import.runner', $this->getMockBuilder('Mathielen\ImportEngine\Import\Run\ImportRunner')->disableOriginalConstructor()->getMock());

        $sl
            ->expects($this->any())
            ->method('getStorage')
            ->will($this->returnValue($this->getMock('Mathielen\ImportEngine\Storage\StorageInterface')));
        $ib
            ->expects($this->any())
            ->method('build')
            ->will($this->returnValue(new ImportRun(new ImportConfiguration())));

        $this->command = new ImportCommand();
        $this->command->setContainer($this->container);
    }

    /**
     * @dataProvider getRunData
     */
    public function testRun(array $input, $parsedSourceId)
    {
        $this->container->get('mathielen_importengine.import.storagelocator')
            ->expects($this->once())
            ->method('selectStorage')
            ->with(array_key_exists('source_provider', $input)?$input['source_provider']:'default', $parsedSourceId)
            ->will($this->returnValue(new StorageSelection('impl')));

        $input = new ArrayInput($input, $this->command->getDefinition());
        $output = new TestOutput();

        $this->command->run($input, $output);
    }

    public function getRunData()
    {
        return array(
            array(array('source_id'=>'source_id', '--context'=>'key=value&otherkey=othervalue'), 'source_id'),
            array(array('source_id'=>'service.method?arg1=abc'), array('service'=>'service', 'method'=>'method', 'arguments'=>array('arg1'=>'abc')))
        );
    }

}

class TestOutput extends Output
{
    public $output = '';

    public function clear()
    {
        $this->output = '';
    }

    protected function doWrite($message, $newline)
    {
        $this->output .= $message.($newline ? "\n" : '');
    }
}
