Mathielen Import Engine Bundle
==========================

Introduction
------------
This is a bundle for the [mathielen/import-engine library](https://github.com/mathielen/import-engine).

Installation
------------

This library is available on [Packagist](https://packagist.org/packages/mathielen/import-engine-bundle):

To install it, run: 

    $ composer require mathielen/import-engine-bundle:dev-master

Then add the bundle to `app/AppKernel.php`:

```
public function registerBundles()
{
    return array(
        ...
        new Mathielen\ImportEngineBundle\MathielenImportEngineBundle(),
        ...
    );
}
```
