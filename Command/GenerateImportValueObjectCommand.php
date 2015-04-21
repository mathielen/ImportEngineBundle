<?php
namespace Mathielen\ImportEngineBundle\Command;

use Mathielen\ImportEngine\Import\Import;
use Mathielen\ImportEngine\Import\Run\ImportRunner;
use Mathielen\ImportEngine\Importer\Importer;
use Mathielen\ImportEngine\Storage\Format\Discovery\FileExtensionDiscoverStrategy;
use Mathielen\ImportEngine\Storage\StorageInterface;
use Mathielen\ImportEngine\Storage\StorageLocator;
use Mathielen\ImportEngine\ValueObject\ImportConfiguration;
use Mathielen\ImportEngineBundle\Generator\ValueObject\FieldFormatGuesser;
use Mathielen\ImportEngineBundle\Generator\ValueObjectGenerator;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Generates value objects representing importing rows.
 */
class GenerateImportValueObjectCommand extends ContainerAwareCommand
{
    /**
     * @see Command
     */
    public function configure()
    {
        $this
            ->addArgument('source_id', InputArgument::REQUIRED, "Id of the demo-source. Different StorageProviders need different id-styles.\n- file/directory: \"<path/to/file>\"\n- doctrine: \"<id of query>\"\n- service: \"<service>.<method>[?arguments_like_url_query]\"")
            ->addArgument('name', InputArgument::REQUIRED, "Classname of the valueobject that should be generated")
            ->addArgument('path', InputArgument::REQUIRED, "Output directory for the class file")
            ->addOption('source_provider', null, InputOption::VALUE_OPTIONAL, 'Id of source provider. If not given it will be default', 'default')
            ->addOption('format', null, InputOption::VALUE_OPTIONAL, "The format of the file (as a file extension). If not given it will be automatically determined.")
            ->addOption('skip-field-format-discovery', null, InputOption::VALUE_NONE, "Do not scan source to determine the field-formats. Every fields will be assigned to the default-field-format")
            ->addOption('default-field-format', null, InputOption::VALUE_OPTIONAL, "Default field format", 'string')
            ->setDescription('Generates a valueobject class file for use with the importengine.')
            ->setHelp(<<<EOT
The <info>generate:import:valueobject</info> command helps you generates new <comment>valueobjects</comment>
for the <info>mathielen/import-engine</info> importer.

<info>What is a valueobject?</info>
A <comment>valueobject</comment> is a small object that represents a simple entity whose equality is not based
on identity: i.e. two value objects are equal when they have the same value, not necessarily being the same object.

<info>Why do I need valueobjects for my importer?</info>
Here, the <comment>valueobject</comment> represents the current dataset that is processed by the importer (ie. a row of a file).
Having a generated class that represents this dataset enables you to explicitly define validation rules and other
related things.

This command can help you generate the <comment>valueobject</comment> class based on a "demo-file" of your import.
EOT
            )
            ->setName('importengine:generate:valueobject')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $sourceId = $input->getArgument('source_id');
        $sourceProvider = $input->getOption('source_provider');
        $clsName = $input->getArgument('name');
        $path = $input->getArgument('path');
        $format = $input->getOption('format');
        $defaultFieldFormat = $input->getOption('default-field-format');

        if (!is_dir($path) || !is_writable($path)) {
            throw new \RuntimeException(sprintf('The directory "%s" is not a directory or cannot be written to.', $path));
        }

        /** @var StorageLocator $storageLocator */
        $storageLocator = $this->getContainer()->get('mathielen_importengine.import.storagelocator');
        $storageSelection = $storageLocator->selectStorage($sourceProvider, $sourceId);

        if (!empty($format)) {
            $storageSelection->addMetadata('format', FileExtensionDiscoverStrategy::fileExtensionToFormat($format));
        }

        $storage = $storageLocator->getStorage($storageSelection);

        if (!$input->getOption('skip-field-format-discovery')) {
            $fieldDefinitions = $this->determineFieldDefinitions($storage, $defaultFieldFormat);
        } else {
            $fieldDefinitions = array_change_key_case(array_fill_keys($storage->getFields(), array('type'=>$defaultFieldFormat)), CASE_LOWER);
        }

        /** @var ValueObjectGenerator $voGenerator */
        $voGenerator = $this->getContainer()->get('mathielen_importengine.generator.valueobject');
        $voGenerator->setSkeletonDirs(__DIR__.'/../Resources/skeleton');

        $filePath = $voGenerator->generate($fieldDefinitions, $clsName, $path);

        $output->writeln("Valueobject class file has been generated and saved to <info>$filePath</info>");
    }

    private function determineFieldDefinitions(StorageInterface $storage, $defaultFieldFormat='string')
    {
        /** @var Importer $importer */
        $importer = $this->getContainer()->get('mathielen_importengine.generator.valueobject.importer');
        $import = Import::build($importer);
        $import->setSourceStorage($storage);

        $importConfiguration = new ImportConfiguration();
        $importConfiguration->setImport($import);
        $importRun = $importConfiguration->toRun();

        /** @var ImportRunner $importRunner */
        $importRunner = $this->getContainer()->get('mathielen_importengine.import.runner');
        $importRunner->run($importRun);

        /** @var FieldFormatGuesser $fieldformatguesser */
        $fieldformatguesser = $this->getContainer()->get('mathielen_importengine.generator.valueobject.fieldformatguesser');

        return $fieldformatguesser->getFieldDefinitionGuess($defaultFieldFormat);
    }

}
