<?php
namespace Mathielen\ImportEngineBundle;

use Mathielen\ImportEngineBundle\Expression\ExpressionLanguageProvider;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class MathielenImportEngineBundle extends Bundle
{

    public function build(ContainerBuilder $container)
    {
        $container->addExpressionLanguageProvider(new ExpressionLanguageProvider());

        return parent::build($container);
    }

}
