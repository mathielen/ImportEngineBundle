<?php
namespace Mathielen\ImportEngineBundle\Command;

use Mathielen\DataImport\Event\ImportItemEvent;
use Mathielen\ImportEngine\Exception\InvalidConfigurationException;
use Mathielen\ImportEngine\Import\ImportBuilder;
use Mathielen\ImportEngine\Import\Run\ImportRunner;
use Mathielen\ImportEngine\Storage\StorageLocator;
use Mathielen\ImportEngine\ValueObject\ImportConfiguration;
use Mathielen\ImportEngine\ValueObject\ImportRun;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this->setName('importengine:import')
            ->setDescription('Imports data with a definied importer')
            ->addArgument('importer', InputArgument::REQUIRED, 'id/name of importer')
            ->addArgument('source_id', InputArgument::REQUIRED, "id of source. Different StorageProviders need different id data.\n- upload, directory: \"<path/to/file>\"\n- doctrine: \"<id of query>\"\n- service: \"<service>.<method>[?arguments_like_url_query]\"")
            ->addArgument('source_provider', InputArgument::OPTIONAL, 'id of source provider', 'default')
            ->addOption('context', 'c', InputOption::VALUE_OPTIONAL, 'Supply optional context information to import. Supply key-value data in query style: key=value&otherkey=othervalue&...')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->getContainer()->has('mathielen_importengine.import.builder') ||
            !$this->getContainer()->has('mathielen_importengine.import.storagelocator') ||
            !$this->getContainer()->has('mathielen_importengine.import.runner')) {
            throw new InvalidConfigurationException("No importengine services have been found. Did you register the bundle in AppKernel and configured at least one importer in config?");
        }

        $progress = new ProgressBar($output);
        $importerName = $input->getArgument('importer');
        $sourceProvider = $input->getArgument('source_provider');
        $sourceId = $this->parseSourceId($input->getArgument('source_id'));

        /** @var StorageLocator $storageLocator */
        $storageLocator = $this->getContainer()->get('mathielen_importengine.import.storagelocator');
        $storageSelection = $storageLocator->selectStorage($sourceProvider, $sourceId);
        $importConfiguration = new ImportConfiguration($storageSelection, $importerName);

        $output->writeln("Commencing import using importer <info>$importerName</info> with source provider <info>$sourceProvider</info> and source id <info>".$input->getArgument('source_id')."</info>");

        /** @var ImportBuilder $importBuilder */
        $importBuilder = $this->getContainer()->get('mathielen_importengine.import.builder');
        $importRun = $importBuilder->build($importConfiguration, 'CLI');

        //copy info from storage to import run
        if ($input->getOption('context') !== null) {
            $context = [];
            parse_str($input->getOption('context'), $context);
            $importRun->setContext($context);
        }
        $importRun->setInfo((array) $storageLocator->getStorage($storageSelection)->info());

        //status callback
        $this->getContainer()->get('event_dispatcher')->addListener('data-import.read', function (ImportItemEvent $event) use ($output, &$progress) {
            /** @var ImportRun $importRun */
            $importRun = $event->getContext();
            $processed = $importRun->getStatistics()['processed'];
            $max = $importRun->getInfo()['count'];

            if ($progress->getMaxSteps() != $max) {
                $progress = new ProgressBar($output, $max);
                $progress->start();
            }

            $progress->setProgress($processed);
        });

        /** @var ImportRunner $importRunner */
        $importRunner = $this->getContainer()->get('mathielen_importengine.import.runner');
        $importRunner->run($importRun);

        $progress->finish();
        $output->writeln('');
        $output->writeln("<info>Import done</info>");
        $output->writeln('');

        $rows = [];
        foreach ($importRun->getStatistics() as $k=>$v) {
            $rows[] = [$k, $v];
        }

        $table = new Table($output);
        $table
            ->setHeaders(array('Statistics'))
            ->setRows($rows)
        ;
        $table->render();

        $output->writeln('');
    }

    private function parseSourceId($sourceId)
    {
        if (preg_match('/[a-zA-Z0-9]+(\.[a-zA-Z0-9]+)+/', $sourceId)) {
            $parsedSourceId = parse_url($sourceId);
            if (array_key_exists('query', $parsedSourceId)) {
                parse_str($parsedSourceId['query'], $parsedSourceId['query']);
            }
            $pathTokens = explode('.', $parsedSourceId['path']);
            $method = array_pop($pathTokens);
            $service = join('.', $pathTokens);

            return array(
                'service' => $service,
                'method' => $method,
                'arguments' => $parsedSourceId['query']
            );
        }

        return $sourceId;
    }

}
