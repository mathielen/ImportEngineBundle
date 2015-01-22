<?php
namespace Mathielen\ImportEngineBundle\Command;

use Infrastructure\Utils;
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
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends ContainerAwareCommand
{

    const MAX_VIOLATION_ERRORS = 10;

    protected function configure()
    {
        $this->setName('importengine:import')
            ->setDescription('Imports data with a definied importer')
            ->addArgument('importer', InputArgument::REQUIRED, 'id/name of importer')
            ->addArgument('source_id', InputArgument::REQUIRED, "id of source. Different StorageProviders need different id data.\n- upload, directory: \"<path/to/file>\"\n- doctrine: \"<id of query>\"\n- service: \"<service>.<method>[?arguments_like_url_query]\"")
            ->addArgument('source_provider', InputArgument::OPTIONAL, 'id of source provider', 'default')
            ->addOption('context', 'c', InputOption::VALUE_OPTIONAL, 'Supply optional context information to import. Supply key-value data in query style: key=value&otherkey=othervalue&...')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit imported rows')
        ;
    }

    protected function validateInput(InputInterface $input)
    {
        if (!$this->getContainer()->has('mathielen_importengine.import.builder') ||
            !$this->getContainer()->has('mathielen_importengine.import.storagelocator') ||
            !$this->getContainer()->has('mathielen_importengine.import.runner')) {
            throw new InvalidConfigurationException("No importengine services have been found. Did you register the bundle in AppKernel and configured at least one importer in config?");
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->validateInput($input);

        $importerName = $input->getArgument('importer');
        $sourceProviderId = $input->getArgument('source_provider');
        $sourceId = $input->getArgument('source_id');
        $context = null;
        if ($input->getOption('context')) {
            $context = [];
            parse_str($input->getOption('context'), $context);
        }

        $this->import($output, $importerName, $sourceProviderId, $sourceId, $context);
    }

    protected function import(OutputInterface $output, $importerName, $sourceProviderId, $sourceId, $context=null)
    {
        $output->writeln("Commencing import using importer <info>$importerName</info> with source provider <info>$sourceProviderId</info> and source id <info>$sourceId</info>");

        $sourceId = $this->parseSourceId($sourceId);
        $progress = new ProgressBar($output);

        /** @var StorageLocator $storageLocator */
        $storageLocator = $this->getContainer()->get('mathielen_importengine.import.storagelocator');
        $storageSelection = $storageLocator->selectStorage($sourceProviderId, $sourceId);
        $importConfiguration = new ImportConfiguration($storageSelection, $importerName);

        /** @var ImportBuilder $importBuilder */
        $importBuilder = $this->getContainer()->get('mathielen_importengine.import.builder');
        $importRun = $importBuilder->build($importConfiguration, Utils::whoAmI().'@CLI');

        $importRun->setInfo((array) $storageLocator->getStorage($storageSelection)->info());
        $importRun->setContext($context);

        //status callback
        $this->getContainer()->get('event_dispatcher')->addListener('data-import.read', function (ImportItemEvent $event) use ($output, &$progress) {
            /** @var ImportRun $importRun */
            $importRun = $event->getContext();
            $stats = $importRun->getStatistics();
            $processed = array_key_exists('processed', $stats)?$stats['processed']:0;
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

        $this->writeStatistics($importRun->getStatistics(), new Table($output));

        if ($importRun->getConfiguration()->getImport()) {
            $this->writeValidationViolations(
                $importRun
                    ->getConfiguration()
                    ->getImport()
                    ->importer()
                    ->validation()
                    ->getViolations(),
                new Table($output));
        }

        $output->writeln('');
    }

    protected function writeValidationViolations(array $violations, Table $table)
    {
        $table
            ->setHeaders(array('Type', 'Line', 'Violation'))
        ;

        $count = 0;
        $count += $this->writeValidationViolationsType('source', $violations, $table);
        $count += $this->writeValidationViolationsType('target', $violations, $table);

        if ($count > 0) {
            $table->render();
        }
    }

    /**
     * @return int number of written violation messages
     */
    private function writeValidationViolationsType($type, array $violations, Table $table)
    {
        if (!array_key_exists($type, $violations)) {
            return 0;
        }

        $i = 0;
        foreach ($violations[$type] as $line=>$validations) {
            foreach ($validations as $validation) {
                $table->addRow(array($type, $line, $validation));
                $i++;
                if ($i == self::MAX_VIOLATION_ERRORS) {
                    $table->addRow(new TableSeparator());
                    $table->addRow(array(null, null, 'There are more errors...'));

                    return $i;
                }
            }
        }

        return $i;
    }

    protected function writeStatistics(array $statistics, Table $table)
    {
        $rows = [];
        foreach ($statistics as $k=>$v) {
            $rows[] = [$k, $v];
        }

        $table
            ->setHeaders(array('Statistics'))
            ->setRows($rows)
        ;
        $table->render();
    }

    private function parseSourceId($sourceId)
    {
        if (preg_match('/^[a-zA-Z0-9]+(\.[a-zA-Z0-9]+)+/', $sourceId)) {
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
