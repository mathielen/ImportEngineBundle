<?php
namespace Mathielen\ImportEngineBundle\Generator;

use Sensio\Bundle\GeneratorBundle\Generator\Generator;
use Symfony\Component\Filesystem\Filesystem;

class ValueObjectGenerator extends Generator
{

    public function generate(array $fieldDefinitions = array(), $clsName, $path)
    {
        $fileName = str_replace("\\", '/', $clsName) . '.php';
        $file = $path . '/' . $fileName;

        $clsToken = explode('\\', $clsName);
        $class = array_pop($clsToken);
        $namespace = join('\\', $clsToken);

        $parameters = array(
            'namespace' => $namespace,
            'class' => $class,
            'field_definitions' => $fieldDefinitions
        );

        $this->renderFile('ValueObject.php.twig', $file, $parameters);

        return $file;
    }

}
