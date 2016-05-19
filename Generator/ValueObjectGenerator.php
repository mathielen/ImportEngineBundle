<?php

namespace Mathielen\ImportEngineBundle\Generator;

use Sensio\Bundle\GeneratorBundle\Generator\Generator;

class ValueObjectGenerator extends Generator
{
    public function generate(array $fieldDefinitions = array(), $clsName, $path)
    {
        $fileName = str_replace('\\', '/', $clsName).'.php';
        $file = $path.'/'.$fileName;

        $clsToken = explode('\\', $clsName);
        $class = array_pop($clsToken);
        $namespace = implode('\\', $clsToken);

        $parameters = array(
            'namespace' => $namespace,
            'class' => $class,
            'field_definitions' => $fieldDefinitions,
        );

        $this->renderFile('ValueObject.php.twig', $file, $parameters);

        return $file;
    }
}
