<?php
namespace Mathielen\ImportEngineBundle\DependencyInjection;

use Mathielen\ImportEngine\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\ExpressionLanguage\Expression;

class MathielenImportEngineExtension extends Extension
{

    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        if (!empty($config['importers'])) {
            $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
            $loader->load('services.xml');

            $this->parseConfig($config, $container);
        }
    }

    private function parseConfig(array $config, ContainerBuilder $container)
    {
        $storageLocatorDef = $container->findDefinition('mathielen_importengine.import.storagelocator');
        foreach ($config['storageprovider'] as $name => $sourceConfig) {
            $this->addStorageProviderDef($storageLocatorDef, $sourceConfig, $name);
        }

        $importerRepositoryDef = $container->findDefinition('mathielen_importengine.importer.repository');
        foreach ($config['importers'] as $name => $importConfig) {
            $finderDef = null;
            if (isset($importConfig['preconditions'])) {
                $finderDef = $this->generateFinderDef($importConfig['preconditions']);
            }

            $objectFactoryDef = null;
            if (isset($importConfig['object_factory'])) {
                $objectFactoryDef = $this->generateObjectFactoryDef($importConfig['object_factory']);
            }

            $importerRepositoryDef->addMethodCall('register', array(
                $name,
                $this->generateImporterDef($importConfig, $objectFactoryDef),
                $finderDef
            ));
        }
    }

    private function generateObjectFactoryDef(array $config)
    {
        if ($config['type'] == 'jms_serializer') {
            return new Definition('Mathielen\DataImport\Writer\ObjectWriter\JmsSerializerObjectFactory', array(
                $config['class'],
                new Reference('jms_serializer')));
        }

        return new Definition('Mathielen\DataImport\Writer\ObjectWriter\DefaultObjectFactory', array($config['class']));
    }

    /**
     * @return \Symfony\Component\DependencyInjection\Definition
     */
    private function generateFinderDef(array $finderConfig)
    {
        $finderDef = new Definition('Mathielen\ImportEngine\Importer\ImporterPrecondition');

        if (isset($finderConfig['filename'])) {
            foreach ($finderConfig['filename'] as $conf) {
                $finderDef->addMethodCall('filename', array($conf));
            }
        }

        if (isset($finderConfig['format'])) {
            foreach ($finderConfig['format'] as $conf) {
                $finderDef->addMethodCall('format', array($conf));
            }
        }

        if (isset($finderConfig['fieldcount'])) {
            $finderDef->addMethodCall('fieldcount', array($finderConfig['fieldcount']));
        }

        if (isset($finderConfig['fields'])) {
            foreach ($finderConfig['fields'] as $conf) {
                $finderDef->addMethodCall('field', array($conf));
            }
        }

        if (isset($finderConfig['fieldset'])) {
            $finderDef->addMethodCall('fieldset', array($finderConfig['fieldset']));
        }

        return $finderDef;
    }

    /**
     * @return \Symfony\Component\DependencyInjection\Definition
     */
    private function generateImporterDef(array $importConfig, Definition $objectFactoryDef=null)
    {
        $importerDef = new Definition('Mathielen\ImportEngine\Importer\Importer', array(
            $this->getStorageDef($importConfig['target'], $objectFactoryDef)
        ));

        if (isset($importConfig['source'])) {
            $this->setSourceStorageDef($importConfig['source'], $importerDef);
        }

        //enable validation?
        if (isset($importConfig['validation'])) {
            $this->generateValidationDef($importConfig['validation'], $importerDef, $objectFactoryDef);
        }

        //add converters?
        if (isset($importConfig['mappings'])) {
            $this->generateTransformerDef($importConfig['mappings'], $importerDef);
        }

        return $importerDef;
    }

    private function generateTransformerDef(array $mappingOptions, Definition $importerDef)
    {
        $mappingsDef = new Definition('Mathielen\ImportEngine\Mapping\Mappings');

        //set converters
        foreach ($mappingOptions as $field=>$fieldMapping) {
            $converter = null;
            if (isset($fieldMapping['converter'])) {
                $converter = $fieldMapping['converter'];
            }

            if (isset($fieldMapping['to'])) {
                $mappingsDef->addMethodCall('add', array(
                    $field,
                    $fieldMapping['to'],
                    $converter
            ));
            } elseif ($converter) {
                $mappingsDef->addMethodCall('setConverter', array(
                    $converter,
                    $field
                ));
            }
        }

        $mappingFactoryDef = new Definition('Mathielen\ImportEngine\Mapping\DefaultMappingFactory', array(
            $mappingsDef
        ));
        $converterProviderDef = new Definition('Mathielen\ImportEngine\Mapping\Converter\Provider\ContainerAwareConverterProvider', array(
            new Reference('service_container')
        ));

        $transformerDef = new Definition('Mathielen\ImportEngine\Transformation\Transformation');
        $transformerDef->addMethodCall('setMappingFactory', array(
            $mappingFactoryDef
        ));
        $transformerDef->addMethodCall('setConverterProvider', array(
            $converterProviderDef
        ));

        $importerDef->addMethodCall('transformation', array(
            $transformerDef
        ));
    }

    private function generateValidatorDef(array $options)
    {
        //eventdispatcher aware source validatorfilter
        $validatorFilterDef = new Definition('Mathielen\DataImport\Filter\ValidatorFilter', array(
            new Reference('validator'),
            $options,
            new Reference('event_dispatcher')
        ));

        return $validatorFilterDef;
    }

    private function generateValidationDef(array $validationConfig, Definition $importerDef, Definition $objectFactoryDef=null)
    {
        $validationDef = new Definition('Mathielen\ImportEngine\Validation\ValidatorValidation', array(
            new Reference('validator')
        ));
        $importerDef->addMethodCall('validation', array(
            $validationDef
        ));

        $validatorFilterDef = $this->generateValidatorDef(
            isset($validationConfig['options'])?$validationConfig['options']:array()
        );

        if (isset($validationConfig['source'])) {
            $validationDef->addMethodCall('setSourceValidatorFilter', array(
                $validatorFilterDef
            ));

            foreach ($validationConfig['source']['constraints'] as $field=>$constraint) {
                $validationDef->addMethodCall('addSourceConstraint', array(
                    $field,
                    new Definition($constraint)
                ));
            }
        }

        //automatically apply class validation
        if (isset($validationConfig['target'])) {

            //using objects as result
            if ($objectFactoryDef) {

                //set eventdispatcher aware target CLASS-validatorfilter
                $validatorFilterDef = new Definition('Mathielen\DataImport\Filter\ClassValidatorFilter', array(
                    new Reference('validator'),
                    $objectFactoryDef,
                    new Reference('event_dispatcher')
                ));

            } else {
                foreach ($validationConfig['target']['constraints'] as $field=>$constraint) {
                    $validationDef->addMethodCall('addTargetConstraint', array(
                        $field,
                        new Definition($constraint)
                    ));
                }
            }

            $validationDef->addMethodCall('setTargetValidatorFilter', array(
                $validatorFilterDef
            ));
        }

        return $validationDef;
    }

    private function setSourceStorageDef(array $sourceConfig, Definition $importerDef)
    {
        $sDef = $this->getStorageDef($sourceConfig, $importerDef);
        $importerDef->addMethodCall('setSourceStorage', array(
            $sDef
        ));
    }

    private function addStorageProviderDef(Definition $storageLocatorDef, $config, $id = 'default')
    {
        $formatDiscoverLocalFileStorageFactoryDef = new Definition('Mathielen\ImportEngine\Storage\Factory\FormatDiscoverLocalFileStorageFactory', array(
            new Definition('Mathielen\ImportEngine\Storage\Format\Discovery\MimeTypeDiscoverStrategy', array(
                array(
                    'text/plain'=>new Definition('Mathielen\ImportEngine\Storage\Format\Factory\CsvAutoDelimiterFormatFactory'),
                    'text/csv'=>new Definition('Mathielen\ImportEngine\Storage\Format\Factory\CsvAutoDelimiterFormatFactory'),
                )
            )),
            new Reference('logger')
        ));

        switch ($config['type']) {
            case 'directory':
                $spFinderDef = new Definition('Symfony\Component\Finder\Finder');
                $spFinderDef->addMethodCall('in', array(
                    $config['uri']
                ));
                $spDef = new Definition('Mathielen\ImportEngine\Storage\Provider\FinderFileStorageProvider', array(
                    $spFinderDef,
                    $formatDiscoverLocalFileStorageFactoryDef
                ));
                break;
            case 'upload':
                $spDef = new Definition('Mathielen\ImportEngine\Storage\Provider\UploadFileStorageProvider', array(
                    $config['uri'],
                    $formatDiscoverLocalFileStorageFactoryDef
                ));
                break;
            case 'doctrine':
                $spDef = new Definition('Mathielen\ImportEngine\Storage\Provider\DoctrineQueryStorageProvider', array(
                    new Reference('doctrine.orm.entity_manager'),
                    $config['queries']
                ));
                break;
            case 'service':
                $spDef = new Definition('Mathielen\ImportEngine\Storage\Provider\ServiceStorageProvider', array(
                    new Reference('service_container'),
                    $config['services']
                ));
                break;
            case 'file':
                $spDef = new Definition('Mathielen\ImportEngine\Storage\Provider\FileStorageProvider', array(
                    $formatDiscoverLocalFileStorageFactoryDef
                ));
                break;
            default:
                throw new InvalidConfigurationException('Unknown type for storage provider: '.$config['type']);
        }

        $storageLocatorDef->addMethodCall('register', array(
            $id,
            $spDef
        ));
    }

    private function getStorageFileDefinitionFromUri($uri)
    {
        if (substr($uri, 0, 2) === '@=') {
            $uri = new Expression(substr($uri, 2));
        }

        return new Definition('SplFileInfo', array(
            $uri
        ));
    }

    /**
     * @return Definition
     */
    private function getStorageDef(array $config, Definition $objectFactoryDef=null)
    {
        switch ($config['type']) {
            case 'file':
                $fileDef = $this->getStorageFileDefinitionFromUri($config['uri']);

                $format = $config['format'];
                $storageDef = new Definition('Mathielen\ImportEngine\Storage\LocalFileStorage', array(
                    $fileDef,
                    new Definition('Mathielen\ImportEngine\Storage\Format\\'.ucfirst($format['type'])."Format", $format['arguments'])
                ));

                break;
            case 'doctrine':
                $storageDef = new Definition('Mathielen\ImportEngine\Storage\DoctrineStorage', array(
                    new Reference('doctrine.orm.entity_manager'),
                    $config['entity']
                ));

                break;
            case 'service':
                $storageDef = new Definition('Mathielen\ImportEngine\Storage\ServiceStorage', array(
                    array(new Reference($config['service']), $config['method']), //callable
                    array(),
                    $objectFactoryDef //from parameter array
                ));

                break;
            default:
                throw new InvalidConfigurationException('Unknown type for storage: '.$config['type']);
        }

        return $storageDef;
    }

    public function getAlias()
    {
        return 'mathielen_import_engine';
    }
}
