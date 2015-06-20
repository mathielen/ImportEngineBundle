<?php
namespace Mathielen\ImportEngineBundle\Tests\Command;

use Mathielen\ImportEngine\Event\ImportConfigureEvent;
use Mathielen\ImportEngineBundle\Utils;
use Mathielen\ImportEngine\Import\Import;
use Mathielen\ImportEngine\Importer\Importer;
use Mathielen\ImportEngine\ValueObject\ImportConfiguration;
use Mathielen\ImportEngine\ValueObject\ImportRequest;
use Mathielen\ImportEngine\ValueObject\ImportRun;
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
        $ib = $this->getMockBuilder('Mathielen\ImportEngine\Import\ImportBuilder')->disableOriginalConstructor()->getMock();

        $this->container = new ContainerBuilder();
        $this->container->set('event_dispatcher', $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface'));
        $this->container->set('mathielen_importengine.import.builder', $ib);
        $this->container->set('mathielen_importengine.import.runner', $this->getMockBuilder('Mathielen\ImportEngine\Import\Run\ImportRunner')->disableOriginalConstructor()->getMock());

        $this->command = new ImportCommand();
        $this->command->setContainer($this->container);
    }

    public function testRunLimit()
    {
        $this->container->get('mathielen_importengine.import.builder')
            ->expects($this->once())
            ->method('build')
            ->will($this->returnValue(
                new Import(
                    new Importer(
                        $this->getMock('Mathielen\ImportEngine\Storage\StorageInterface')
                    ),
                    $this->getMock('Mathielen\ImportEngine\Storage\StorageInterface'),
                    new ImportRun(new ImportConfiguration())
                )
            ));

        $this->container->get('event_dispatcher')
            ->expects($this->exactly(2))
            ->method('addListener')
            ->withConsecutive(
                array(
                    ImportConfigureEvent::AFTER_BUILD,
                    $this->anything()
                )
            );

        $this->container->get('mathielen_importengine.import.runner')
            ->expects($this->once())
            ->method('run');

        $input = new ArrayInput(array('--limit'=>1, '--importer'=>'abc', 'source_id'), $this->command->getDefinition());
        $output = new TestOutput();

        $this->command->run($input, $output);
    }

    public function testRunDryrun()
    {
        $this->container->get('mathielen_importengine.import.builder')
            ->expects($this->once())
            ->method('build')
            ->will($this->returnValue(
                new Import(
                    new Importer(
                        $this->getMock('Mathielen\ImportEngine\Storage\StorageInterface')
                    ),
                    $this->getMock('Mathielen\ImportEngine\Storage\StorageInterface'),
                    new ImportRun(new ImportConfiguration())
                )
            ));

        $this->container->get('mathielen_importengine.import.runner')
            ->expects($this->once())
            ->method('dryrun');

        $input = new ArrayInput(array('--dryrun'=>true, '--importer'=>'abc', 'source_id'), $this->command->getDefinition());
        $output = new TestOutput();

        $this->command->run($input, $output);
    }

    /**
     * @dataProvider getRunData
     */
    public function testRun(array $input, $parsedSourceId)
    {
        $this->container->get('mathielen_importengine.import.builder')
            ->expects($this->once())
            ->method('build')
            ->with(new ImportRequest($parsedSourceId, 'default', null, Utils::whoAmI().'@CLI'))
            ->will($this->returnValue(
                new Import(
                    new Importer(
                        $this->getMock('Mathielen\ImportEngine\Storage\StorageInterface')
                    ),
                    $this->getMock('Mathielen\ImportEngine\Storage\StorageInterface'),
                    new ImportRun(new ImportConfiguration())
                )
            ));

        $this->container->get('mathielen_importengine.import.runner')
            ->expects($this->once())
            ->method('run');

        $input = new ArrayInput($input, $this->command->getDefinition());
        $output = new TestOutput();

        $this->command->run($input, $output);
    }

    public function getRunData()
    {
        return array(
            array(array('source_id'=>'source_id'), 'source_id'),
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
