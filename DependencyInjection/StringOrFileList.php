<?php

namespace Mathielen\ImportEngineBundle\DependencyInjection;

use Symfony\Component\Finder\Iterator\RecursiveDirectoryIterator;

class StringOrFileList extends \ArrayObject
{
    public function __construct(array $listOrStringsOrFiles)
    {
        foreach ($listOrStringsOrFiles as $k => &$v) {
            if (filter_var($v, FILTER_VALIDATE_URL)) {
                //nothing
            } elseif (is_dir($v)) {
                $iterator = new RecursiveDirectoryIterator($v, \FilesystemIterator::KEY_AS_PATHNAME);
                $iterator = new \RecursiveIteratorIterator($iterator);

                /** @var \Symfony\Component\Finder\SplFileInfo $file */
                foreach ($iterator as $file) {
                    if (is_file($file)) {
                        $listOrStringsOrFiles[$file->getRelativePathname()] = $file;
                    }
                }

                unset($listOrStringsOrFiles[$k]);
            } elseif (is_file($v)) {
                $v = new \SplFileInfo($v);
            } else {
                throw new \RuntimeException("Unknown value type $v");
            }
        }

        parent::__construct($listOrStringsOrFiles);
    }

    public function offsetGet($offset)
    {
        //offset could be myfile.sql?targetconnection
        $url = parse_url($offset);
        $offset = $url['path'];

        if (!parent::offsetExists($offset)) {
            return $this->checkStreamWrapper($offset);
        }

        $v = parent::offsetGet($offset);
        if ($v instanceof \SplFileInfo) {
            return file_get_contents($v);
        }

        return $v;
    }

    private function checkStreamWrapper($offset)
    {
        foreach ($this as $k => &$v) {
            if (filter_var($v, FILTER_VALIDATE_URL)) {
                $path = $v.'/'.$offset;

                if (file_exists($path)) {
                    return file_get_contents($path);
                }
            }
        }

        throw new \InvalidArgumentException("Item with id '$offset' could not be found.");
    }
}