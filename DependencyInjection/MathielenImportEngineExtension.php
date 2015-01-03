<?php
namespace Mathielen\ImportEngineBundle\DependencyInjection;

use Mathielen\ImportEngine\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;

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
            if (array_key_exists('preconditions', $importConfig)) {
                $finderDef = $this->generateFinderDef($importConfig['preconditions']);
            }

            $objectFactoryDef = null;
            if (array_key_exists('object_factory', $importConfig)) {
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

        if (array_key_exists('filename', $finderConfig)) {
            foreach ($finderConfig['filename'] as $conf) {
                $finderDef->addMethodCall('filename', array($conf));
            }
        }

        if (array_key_exists('format', $finderConfig)) {
            foreach ($finderConfig['format'] as $conf) {
                $finderDef->addMethodCall('format', array($conf));
            }
        }

        if (array_key_exists('fieldcount', $finderConfig)) {
            $finderDef->addMethodCall('fieldcount', array($finderConfig['fieldcount']));
        }

        if (array_key_exists('fields', $finderConfig)) {
            foreach ($finderConfig['fields'] as $conf) {
                $finderDef->addMethodCall('field', array($conf));
            }
        }

        if (array_key_exists('fieldset', $finderConfig)) {
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

        if (array_key_exists('source', $importConfig)) {
            $this->setSourceStorageDef($importConfig['source'], $importerDef);
        }

        //enable validation?
        if (array_key_exists('validation', $importConfig)) {
            $this->generateValidationDef($importConfig['validation'], $importerDef, $objectFactoryDef);
        }

        return $importerDef;
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
        $importerDef->addMethodCall('setValidation', array(
            $validationDef
        ));

        $validatorFilterDef = $this->generateValidatorDef(
            array_key_exists('options', $validationConfig)?$validationConfig['options']:array()
        );

        if (array_key_exists('source', $validationConfig)) {
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
        if (array_key_exists('target', $validationConfig)) {

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
        switch ($config['type']) {
            case 'directory':
                $spFinderDef = new Definition('Symfony\Component\Finder\Finder');
                $spFinderDef->addMethodCall('in', array(
                    $config['uri']
                ));
                $spDef = new Definition('Mathielen\ImportEngine\Storage\Provider\FinderFileStorageProvider', array(
                    $spFinderDef
                ));
                break;
            case 'upload':
                $spDef = new Definition('Mathielen\ImportEngine\Storage\Provider\UploadFileStorageProvider', array(
                    $config['uri']
                ));
                break;
            case 'doctrine':
                $spDef = new Definition('Mathielen\ImportEngine\Storage\Provider\DoctrineQueryStorageProvider', array(
                    new Reference('doctrine.orm.entity_manager'),
                    array($config['queries'])
                ));
                break;
            case 'service':
                $spDef = new Definition('Mathielen\ImportEngine\Storage\Provider\ServiceStorageProvider', array(
                    new Reference('service_container'),
                    $config['services']
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

    /**
     * @return Definition
     */
    private function getStorageDef(array $config, Definition $objectFactoryDef=null)
    {
        switch ($config['type']) {
            case 'file':
                $fileDef = new Definition('SplFileInfo', array(
                    $config['uri']
                ));

                $storageDef = new Definition('Mathielen\ImportEngine\Storage\LocalFileStorage', array(
                    $fileDef,
                    new Definition("Mathielen\ImportEngine\Storage\Format\\".ucfirst($config['format'])."Format")
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
