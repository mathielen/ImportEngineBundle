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
It provides an easy way to configure a full-blown data importer for you symfony2 project.

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
        default:
            type: upload                        #[upload, service, array, doctrine, file]
            path: "%kernel.root_dir%/Resources/import"

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
                type: jms_serializer        #[jms_serializer, ~]
                class: Acme\DemoBundle\ValueObject\MyImportedRow

            #validate imported data
            validation:
                source:                     #add constraints to source fields
                    header1: email
                    header2: notempty
                target: ~                   #activate validation against generated object from object-factory (via annotations, xml)
                                            #or supply list of constraints like in source

            #target of import
            target:
                type: service               #[service, array, doctrine, file]
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

Todos
------------
* Constraints resolution (name to class)
* Xml Configuration with validation constraint lists doesnt work
