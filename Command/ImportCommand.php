<?php
namespace Mathielen\ImportEngineBundle\Command;

use Mathielen\ImportEngine\Exception\InvalidConfigurationException;
use Mathielen\ImportEngine\Import\ImportBuilder;
use Mathielen\ImportEngine\Import\Run\ImportRunner;
use Mathielen\ImportEngine\Storage\StorageLocator;
use Mathielen\ImportEngine\ValueObject\ImportConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this->setName('importengine:import')
            ->setDescription('Imports data with a definied importer')
            ->addArgument('importer', InputArgument::REQUIRED, 'id/name of importer')
            ->addArgument('source_id', InputArgument::REQUIRED, "id of source. Different StorageProviders need different id data.\n- upload, directory: \"<path/to/file>\"\n- doctrine: \"<id of query>\"\n- service: \"<service>.<method>[?arguments_like_url_query]\"")
            ->addArgument('source_provider', InputArgument::OPTIONAL, 'id of source provider', 'default');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->getContainer()->has('mathielen_importengine.import.builder')) {
            throw new InvalidConfigurationException("No importengine builder service has been found. Did you register the bundle in AppKernel and configured at least one importer in config?");
        }

        $importerName = $input->getArgument('importer');
        $sourceProvider = $input->getArgument('source_provider');
        $sourceId = $this->parseSourceId($input->getArgument('source_id'));

        /** @var StorageLocator $storageLocator */
        $storageLocator = $this->getContainer()->get('mathielen_importengine.import.storagelocator');
        $storageSelection = $storageLocator->selectStorage($sourceProvider, $sourceId);
        $importConfiguration = new ImportConfiguration($storageSelection, $importerName);

        /** @var ImportBuilder $importBuilder */
        $importBuilder = $this->getContainer()->get('mathielen_importengine.import.builder');
        $importBuilder->build($importConfiguration);

        /** @var ImportRunner $importRunner */
        $importRunner = $this->getContainer()->get('mathielen_importengine.import.runner');
        $importRun = $importRunner->run($importConfiguration->toRun());

        print_r($importRun->getStatistics());
    }

    private function parseSourceId($sourceId)
    {
        $sourceId = parse_url($sourceId);
        @parse_str($sourceId['query'], $sourceId['query']);
        $pathTokens = explode('.', $sourceId['path']);
        $method = array_pop($pathTokens);
        $service = join('.', $pathTokens);

        return array(
            'service' => $service,
            'method' => $method,
            'arguments' => array($sourceId['query'])
        );
    }

}
