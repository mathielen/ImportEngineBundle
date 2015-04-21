Mathielen Import Engine Bundle
==========================

[![Build Status](https://travis-ci.org/mathielen/ImportEngineBundle.png?branch=master)](https://travis-ci.org/mathielen/ImportEngineBundle)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/mathielen/ImportEngineBundle/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/mathielen/ImportEngineBundle/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/mathielen/ImportEngineBundle/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/mathielen/ImportEngineBundle/?branch=master)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/16f2af0e-9318-47f7-bd12-d3f07caf1d21/mini.png)](https://insight.sensiolabs.com/projects/16f2af0e-9318-47f7-bd12-d3f07caf1d21)
[![Latest Stable Version](https://poser.pugx.org/mathielen/import-engine-bundle/v/stable.png)](https://packagist.org/packages/mathielen/import-engine-bundle)


Introduction
------------
This is a bundle for the [mathielen/import-engine library](https://github.com/mathielen/import-engine).
It provides an easy way to configure a full-blown data importer for your symfony2 project.

Installation
------------
This library is available on [Packagist](https://packagist.org/packages/mathielen/import-engine-bundle):

To install it, run: 

```bash
$ composer require mathielen/import-engine-bundle:dev-master
```

Then add the bundle to `app/AppKernel.php`:

```php
public function registerBundles()
{
    return array(
        ...
        new Mathielen\ImportEngineBundle\MathielenImportEngineBundle(),
        ...
    );
}
```

Configuration
------------
Add your importer configurations in your `app/config/config.yml`.

Full example:
```yaml
mathielen_import_engine:
    #configure storageproviders, that are used in all importers
    storageprovider:
        upload:
            type: upload
            uri: "%kernel.root_dir%/Resources/import"
        local:
            type: directory
            uri: /tmp/somedir
        doctrine:
            type: doctrine
            queries:
                - SELECT id FROM Acme\DemoBundle\Entity\Person P WHERE P.age > 10
                - Acme\DemoBundle\Entity\ImportData
        services:
            type: service
            services:
                #the services export_serviceA and export_serviceB must be configured in DIC
                export_serviceA: [exportMethod1, exportMethod2] #restrict to specific methods of service
                export_serviceB: ~ #every method of service can be used
                 
    #configure your Importers
    importers:
        your_importer_name:

            #automaticly recognize this importer by meeting of the conditions below
            preconditions:
                format: excel               #format of data must be [csv, excel, xml]
                fieldcount: 2               #must have this number of fields
                fields:                     #these fields must exist (order is irrelevant)
                    - 'header2'
                    - 'header1'
                fieldset:                   #all fields must exist exactly this order
                    - 'header1'
                    - 'header2'
                filename: 'somefile.xls'    #filename must match one of these regular expression(s) (can be a list)

            #use an object-factory to convert raw row-arrays to target objects
            object_factory:
                type: jms_serializer        #[jms_serializer, default]
                class: Acme\DemoBundle\ValueObject\MyImportedRow

            #add mapping
            mappings:
                #simple a-to-b mapping
                source-field1: target-field1
                
                #convert the field (but dont map)
                source-field2: 
                    #converts excel's date-field to a Y-m-d string (you can use your own service-id here)
                    converter: mathielen_importengine.converter.excel.genericdate
                        
                #map and convert
                source-field3:
                    to: target-field3
                    converter: upperCase    #use a converter that was registered with the converter-provider

            #validate imported data
            validation:
                source:                     #add constraints to source fields
                    header1: email
                    header2: notempty
                target: ~                   #activate validation against generated object from object-factory (via annotations, xml)
                                            #or supply list of constraints like in source

            #target of import
            target:
                type: service               #[service, doctrine, file]
                service: import_service     #service name in DIC
                method: processImportRow    #method to invoke on service
```

Minimum example:
```yaml
mathielen_import_engine:
    importers:
        minimum_importer:
            target:
                type: file
                uri: /tmp/myfile.csv
                format: csv
```

Check out the Testsuite for more information.

Usage
------------

### On the command line

#### Let the framework discover which importer suites best (auto discovery) ####
```bash
$ app/console importengine:import /tmp/somedir/myfile.csv
```

#### Import myfile.csv with "your_importer_name" importer ####
```bash
$ app/console importengine:import -i your_importer_name /tmp/somedir/myfile.csv
```

#### Generate a [JMS Serializer](http://jmsyst.com/libs/serializer)-annotated ValueObject class for an arbitrary import source (ie. a file)
```bash
$ app/console importengine:generate:valueobject data/myfile.csv Acme\\ValueObject\\MyFileRow src
```

### Use the importer within a controller / service

```php
use Mathielen\ImportEngine\ValueObject\ImportConfiguration;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class DemoController extends Controller
{

    /**
     * @Route("/import", name="_demo_import")
     * @Template()
     */
    public function importAction(Request $request)
    {
        //handle the uploaded file
        $storageLocator = $this->container->get('mathielen_importengine.import.storagelocator');
        $storageSelection = $storageLocator->selectStorage('default', $request->files->getIterator()->current());

        //create a new import configuration with your file for the specified importer
        //you can also use auto-discovery with preconditions (see config above and omit 2nd parameter here)
        $importConfiguration = new ImportConfiguration($storageSelection, 'your_importer_name');

        //build the import engine
        $importBuilder = $this->container->get('mathielen_importengine.import.builder');
        $importBuilder->build($importConfiguration);

        //run the import
        $importRunner = $this->container->get('mathielen_importengine.import.runner');
        $importRun = $importRunner->run($importConfiguration->toRun());

        return $importRun->getStatistics();
    }

}
```
