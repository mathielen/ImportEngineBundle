<?php
namespace Mathielen\ImportEngineBundle\Command;

use Ddeboer\DataImport\Filter\OffsetFilter;
use Mathielen\DataImport\Event\ImportItemEvent;
use Mathielen\ImportEngine\Event\ImportConfigureEvent;
use Mathielen\ImportEngine\Event\ImportRequestEvent;
use Mathielen\ImportEngine\Exception\InvalidConfigurationException;
use Mathielen\ImportEngine\Import\ImportBuilder;
use Mathielen\ImportEngine\Import\Run\ImportRunner;
use Mathielen\ImportEngine\ValueObject\ImportRequest;
use Mathielen\ImportEngine\ValueObject\ImportRun;
use Mathielen\ImportEngineBundle\Utils;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\ConstraintViolation;

class ImportCommand extends ContainerAwareCommand
{

    const MAX_VIOLATION_ERRORS = 10;

    protected function configure()
    {
        $this->setName('importengine:import')
            ->setDescription('Imports data with a definied importer')
            ->addArgument('source_id', InputArgument::REQUIRED, "id of source. Different StorageProviders need different id data.\n- upload, directory: \"<path/to/file>\"\n- doctrine: \"<id of query>\"\n- service: \"<service>.<method>[?arguments_like_url_query]\"")
            ->addArgument('source_provider', InputArgument::OPTIONAL, 'id of source provider', 'default')
            ->addOption('importer', 'i', InputOption::VALUE_OPTIONAL, 'id/name of importer')
            ->addOption('context', 'c', InputOption::VALUE_OPTIONAL, 'Supply optional context information to import. Supply key-value data in query style: key=value&otherkey=othervalue&...')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit imported rows')
            ->addOption('dryrun', 'd', InputOption::VALUE_NONE, 'Do not import - Validation only')
        ;
    }

    protected function validateInput(InputInterface $input)
    {
        if (!$this->getContainer()->has('mathielen_importengine.import.builder') ||
            !$this->getContainer()->has('mathielen_importengine.import.runner')) {
            throw new InvalidConfigurationException("No importengine services have been found. Did you register the bundle in AppKernel and configured at least one importer in config?");
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->validateInput($input);

        $importerId = $input->getOption('importer');
        $sourceProviderId = $input->getArgument('source_provider');
        $sourceId = $input->getArgument('source_id');
        $isDryrun = $input->getOption('dryrun');
        if ($context = $input->getOption('context')) {
            //parse key=value&key=value string to array
            if (strpos($context, '=') !== false) {
                parse_str($input->getOption('context'), $context);
            }
        }
        $limit = $input->getOption('limit');

        $this->import($output, $importerId, $sourceProviderId, $sourceId, $context, $limit, $isDryrun);
    }

    protected function import(OutputInterface $output, $importerId, $sourceProviderId, $sourceId, $context=null, $limit=null, $isDryrun=false)
    {
        $output->writeln("Commencing ".($isDryrun?'<comment>dry-run</comment> ':'')."import using importer ".(empty($importerId)?'<comment>unknown</comment>':"<info>$importerId</info>")." with source provider <info>$sourceProviderId</info> and source id <info>$sourceId</info>");

        $sourceId = Utils::parseSourceId($sourceId);
        $progress = new ProgressBar($output);

        //set limit
        if ($limit) {
            $this->getContainer()->get('event_dispatcher')->addListener(ImportConfigureEvent::AFTER_BUILD, function (ImportConfigureEvent $event) use ($limit) {
                $event->getImport()->importer()->filters()->add(new OffsetFilter(0, $limit));
            });
        }

        //show discovered importer id
        if (empty($importerId)) {
            $this->getContainer()->get('event_dispatcher')->addListener(ImportRequestEvent::DISCOVERED, function (ImportRequestEvent $event) use ($output) {
                $importerId = $event->getImportRequest()->getImporterId();
                $output->writeln("Importer discovered: <info>$importerId</info>");
            });
        }

        /** @var ImportBuilder $importBuilder */
        $importBuilder = $this->getContainer()->get('mathielen_importengine.import.builder');

        $importRequest = new ImportRequest($sourceId, $sourceProviderId, $importerId, Utils::whoAmI().'@CLI');

        $import = $importBuilder->build($importRequest);

        //apply context info from commandline
        $importRun = $import->getRun();
        $importRun->setContext($context);

        //status callback
        $this->getContainer()->get('event_dispatcher')->addListener(ImportItemEvent::AFTER_READ, function (ImportItemEvent $event) use ($output, &$progress) {
            /** @var ImportRun $importRun */
            $importRun = $event->getContext()->getRun();
            $stats = $importRun->getStatistics();
            $processed = isset($stats['processed'])?$stats['processed']:0;
            $max = $importRun->getInfo()['count'];

            if ($progress->getMaxSteps() != $max) {
                $progress = new ProgressBar($output, $max);
                $progress->start();
            }

            $progress->setProgress($processed);
        });

        /** @var ImportRunner $importRunner */
        $importRunner = $this->getContainer()->get('mathielen_importengine.import.runner');
        if ($isDryrun) {
            $importRunner->dryRun($import);
        } else {
            $importRunner->run($import);
        }

        $progress->finish();
        $output->writeln('');
        $output->writeln("<info>Import done</info>");
        $output->writeln('');

        $this->writeStatistics($importRun->getStatistics(), new Table($output));

        $this->writeValidationViolations(
            $import
                ->importer()
                ->validation()
                ->getViolations(),
            new Table($output));

        $output->writeln('');
    }

    protected function writeValidationViolations(array $violations, Table $table)
    {
        if (empty($violations)) {
            return;
        }
        $violations = $violations['source'] + $violations['target'];

        $table
            ->setHeaders(array('Constraint', 'Occurrences (lines)'))
        ;

        $tree = [];
        foreach ($violations as $line=>$validations) {
            /** @var ConstraintViolation $validation */
            foreach ($validations as $validation) {
                $key = $validation->__toString();
                if (!isset($tree[$key])) {
                    $tree[$key] = [];
                }
                $tree[$key][] = $line;
            }
        }

        $i = 0;
        foreach ($tree as $violation=>$lines) {
            $table->addRow([$violation, join(', ', Utils::numbersToRangeText($lines))]);
            ++$i;

            if ($i === self::MAX_VIOLATION_ERRORS) {
                $table->addRow(new TableSeparator());
                $table->addRow(array(null, 'There are more errors...'));

                break;
            }
        }

        if ($i > 0) {
            $table->render();
        }
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

}
