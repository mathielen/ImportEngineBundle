<?php
namespace Mathielen\ImportEngineBundle\DependencyInjection;

class Configuration
{

    public static function isDirectory($string)
    {
        return is_dir($string) && is_readable($string);
    }

    public static function isFile($string)
    {
        return is_file($string) && is_readable($string);
    }

    public static function isUpload($string)
    {
        return $string == 'upload';
    }

    public static function isService($string)
    {
        $args = array();
        if (preg_match('/^([a-z_\.]+)::([a-z]+)$/i', $string, $args)) {
            return $args;
        } else {
            return false;
        }
    }

    public static function isDql($string)
    {
        //TODO
    }

    public static function isEntity($string)
    {
        return class_exists($string);
    }

}
