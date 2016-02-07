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
$ composer require mathielen/import-engine-bundle
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
        default:
            type: directory
            uri: /tmp/somedir
        upload:
            type: upload
            uri: "%kernel.root_dir%/Resources/import"
        doctrine:
            type: doctrine
            queries:        #a list of DQL-Statements, Entity-Classnames, filenames or directories
                - SELECT id FROM Acme\DemoBundle\Entity\Person P WHERE P.age > 10   #dql statement
                - Acme\DemoBundle\Entity\ImportData     #entity classname
                - %kernel.root_dir%/dql/mysql.dql       #file with dql statement in it
                - %kernel.root_dir%/other-dql           #directory
        dbal:
            type: dbal
            queries: %kernel.root_dir%/sql/         #same like doctrine
        services:
            type: service
            services:
                #the services export_serviceA and export_serviceB must be configured in DIC
                export_serviceA: [exportMethod1, exportMethod2] #restrict to specific methods of service
                export_serviceB: ~ #every method of service can be used
                 
    #configure your Importers
    importers:
        your_importer_name:
            #some context information that is passed through the whole process
            context:
                key: value

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
                
        another_minimum_importer:
            target:
                type: file
                uri: "@='%kernel.root_dir%/../output_'~date('Y-m-d')~'.csv'"    #this uses symfony expression language
                                                                                #to create the filename. Just prefix your
                                                                                #expression with @=
                format: { type: csv, arguments: [','] }                         #delimiter is now ','                
```

Check out the Testsuite for more information.

Usage
------------

### On the command line

#### Show your configured Import profiles
```bash
$ app/console importengine:list
```

#### Let the framework discover which importer suites best (auto discovery) ####
Uses the storageprovider "default" if not also given as argument.
```bash
$ app/console importengine:import /tmp/somedir/myfile.csv
```

#### Import myfile.csv with "your_importer_name" importer ####
Uses the storageprovider "default" if not also given as argument.
```bash
$ app/console importengine:import -i your_importer_name /tmp/somedir/myfile.csv
```

#### Generate a [JMS Serializer](http://jmsyst.com/libs/serializer)-annotated ValueObject class for an arbitrary import source (ie. a file)
```bash
$ app/console importengine:generate:valueobject data/myfile.csv Acme\\ValueObject\\MyFileRow src
```

### Use the importer within a controller / service

```php
namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{

    /**
     * Import a given file, that was POST'ed to the HTTP-Endpoint /app/import
     * * Using the default sorage provider
     * * The importer is auto-discovered with the format of the file
     *
     * @Route("/app/import", name="homepage")
     * @Method("POST")
     */
    public function importAction(\Symfony\Component\HttpFoundation\Request $request)
    {
        //create the request for the import-engine
        $importRequest = new \Mathielen\ImportEngine\ValueObject\ImportRequest($request->files->getIterator()->current());

        /** @var \Mathielen\ImportEngine\Import\ImportBuilder $importBuilder */
        $importBuilder = $this->container->get('mathielen_importengine.import.builder');
        $import = $importBuilder->build($importRequest);

        /** @var \Mathielen\ImportEngine\Import\Run\ImportRunner $importRunner */
        $importRunner = $this->container->get('mathielen_importengine.import.runner');
        $importRun = $importRunner->run($import);

        return $this->render('default/import.html.twig', $importRun->getStatistics());
    }

}
```
