<?php
namespace Mathielen\ImportEngineBundle\Expression;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

class ExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions()
    {
        return array(
            new ExpressionFunction('date', function ($str) {
                return sprintf('(is_string(%1$s) ? date(%1$s) : %1$s)', $str);
            }, function (array $arguments, $pattern) {
                return date($pattern);
            }),
        );
    }
}
