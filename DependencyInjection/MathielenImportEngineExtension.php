<?php
namespace Mathielen\ImportEngineBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;

class MathielenImportEngineExtension extends Extension
{

    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        $this->parseConfig($configs[0], $container);
    }

    private function parseConfig(array $config, ContainerBuilder $container)
    {
        $storageLocatorDef = $container->findDefinition('mathielen_importengine.import.storagelocator');
        if (array_key_exists('storageprovider', $config)) {
            $this->addStorageProviderDef($storageLocatorDef, $config['storageprovider']);
        } elseif (array_key_exists('storageproviders', $config)) {
            //multiple
            foreach ($config['storageproviders'] as $sourceConfig) {
                $this->addStorageProviderDef($storageLocatorDef, $sourceConfig);
            }
        }

        $importerRepositoryDef = $container->findDefinition('mathielen_importengine.importer.repository');
        foreach ($config['importers'] as $name => $importConfig) {
            $finderDef = null;
            if (array_key_exists('preconditions', $importConfig)) {
                $finderDef = $this->generateFinderDef($importConfig['preconditions']);
            }

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
            $finderDef->addMethodCall('filename', array($finderConfig['filename']));
        } elseif (array_key_exists('filenames', $finderConfig)) {
            foreach ($finderConfig['filenames'] as $conf) {
                $finderDef->addMethodCall('filename', array($conf));
            }
        }

        if (array_key_exists('format', $finderConfig)) {
            $finderDef->addMethodCall('format', array($finderConfig['format']));
        } elseif (array_key_exists('formats', $finderConfig)) {
            foreach ($finderConfig['formats'] as $conf) {
                $finderDef->addMethodCall('format', array($conf));
            }
        }

        if (array_key_exists('fieldcount', $finderConfig)) {
            $finderDef->addMethodCall('fieldcount', array($finderConfig['fieldcount']));
        }

        if (array_key_exists('field', $finderConfig)) {
            $finderDef->addMethodCall('field', array($finderConfig['field']));
        } elseif (array_key_exists('fields', $finderConfig)) {
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
    private function generateImporterDef(array $importConfig, Definition $objectFactoryDef)
    {
        $importerDef = new Definition('Mathielen\ImportEngine\Importer\Importer', array(
            $this->getStorageDef($importConfig['target'], $objectFactoryDef)
        ));

        if (array_key_exists('source', $importConfig)) {
            $this->setSourceStorageDef($importConfig['source'], $importerDef);
        }

        //enable validation?
        if ($importConfig['validation']) {
            $this->generateValidationDef($importConfig['validation'], $importerDef, $objectFactoryDef);
        }

        return $importerDef;
    }

    private function generateValidationDef(array $validationConfig, Definition $importerDef, Definition $objectFactoryDef)
    {
        $validationDef = new Definition('Mathielen\ImportEngine\Validation\ValidatorValidation', array(
            new Reference('validator')
        ));
        $importerDef->addMethodCall('setValidation', array(
            $validationDef
        ));

        if (@$validationConfig['source']) {
            $validatorFilterDef = new Definition('Mathielen\DataImport\Filter\ValidatorFilter', array(
                new Reference('validator'),
                new Reference('event_dispatcher')
            ));
            $validatorFilterDef->addMethodCall('setAllowExtraFields', array(true)); //TODO policy!
            $validatorFilterDef->addMethodCall('setSkipOnViolation', array(false)); //TODO policy!

            //set eventdispatcher aware source validatorfilter
            $validationDef->addMethodCall('setSourceValidatorFilter', array(
                $validatorFilterDef
            ));

            foreach ($validationConfig['source'] as $field=>$constraint) {
                $validationDef->addMethodCall('addSourceConstraint', array(
                    $field,
                    new Definition($constraint)
                ));
            }
        }

        //automatically apply class validation
        if (@$validationConfig['target']) {
            //set eventdispatcher aware target CLASS-validatorfilter
            //TODO class or property validatorfilter?
            $validatorFilterDef = new Definition('Mathielen\DataImport\Filter\ClassValidatorFilter', array(
                new Reference('validator'),
                $objectFactoryDef,
                new Reference('event_dispatcher')
            ));

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
        if (is_array($config)) {
            extract($config);
        } elseif (is_string($config)) {
            switch (true) {
                case Configuration::isDirectory($config):
                    $type = 'directory';
                    $path = $config;
                    break;
                case Configuration::isUpload($config):
                    $type = 'upload';
                    $path = '/tmp'; //TODO
                    break;
                case Configuration::isEntity($config) || Configuration::isDql($config):
                    $type = 'entity';
                    break;
                case Configuration::isFile($config):
                    $type = 'file';
                    $uri = $config;
                    $format = 'csv'; //TODO default?
                    break;
            }
        }

        switch ($type) {
            case 'directory':
                $spFinderDef = new Definition('Symfony\Component\Finder\Finder');
                $spFinderDef->addMethodCall('in', array(
                    $path
                ));
                $spDef = new Definition('Mathielen\ImportEngine\Storage\Provider\FinderFileStorageProvider', array(
                    $spFinderDef
                ));
                break;
            case 'upload':
                $spDef = new Definition('Mathielen\ImportEngine\Storage\Provider\UploadFileStorageProvider', array(
                    $path
                ));
                break;
            case 'entity':
                break;
            default:
                throw new \InvalidArgumentException("Unknown type: $type");
        }

        $storageLocatorDef->addMethodCall('register', array(
            $id,
            $spDef
        ));
    }

    /**
     * @return Definition
     */
    private function getStorageDef(array $config, Definition $objectFactoryDef)
    {
        if (is_array($config)) {
            extract($config);
        } elseif (is_string($config)) {
            switch (true) {
                case Configuration::isEntity($config):
                    $type = 'entity';
                    break;
                case Configuration::isFile($config):
                    $type = 'file';
                    $uri = $config;
                    $format = 'csv'; //TODO default?
                    break;
                case $serviceInfo = Configuration::isService($config):
                    $type = 'service';
                    $service = $serviceInfo[1];
                    $method = $serviceInfo[2];
                    //$object_factory = ??
                    break;
                default:
                    $type = 'unknown';
            }
        }

        switch ($type) {
            case 'file':
                $fileDef = new Definition('SplFileObject', array(
                    $uri,
                    'w'
                ));

                $storageDef = new Definition('Mathielen\ImportEngine\Storage\LocalFileStorage', array(
                    $fileDef,
                    new Definition("Mathielen\ImportEngine\Storage\Format\\".ucfirst($format)."Format")
                ));

                break;
            case 'entity':
                // $qb = new Definition('Doctrine\ORM\QueryBuilder');
                // $qb->setFactoryService('doctrine.orm.entity_manager');
                // $qb->setFactoryMethod('createQueryBuilder');

                $storageDef = new Definition('Mathielen\ImportEngine\Storage\DoctrineStorage', array(
                    new Reference('doctrine.orm.entity_manager'),
                    $config
                ));

                break;
            case 'service':
                $storageDef = new Definition('Mathielen\ImportEngine\Storage\ServiceStorage', array(
                    array(new Reference($service), $method), //callable
                    $objectFactoryDef //from parameter array
                ));

                break;
            default:
                throw new \InvalidArgumentException("Unknown type: $type");
        }

        return $storageDef;
    }

    public function getAlias()
    {
        return 'mathielen_import_engine';
    }
}
