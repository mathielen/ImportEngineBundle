<?php
namespace Mathielen\ImportEngineBundle\DependencyInjection;

use Symfony\Component\Finder\Iterator\RecursiveDirectoryIterator;

class StringOrFileList extends \ArrayObject
{

    public function __construct(array $listOrStringsOrFiles)
    {
        foreach ($listOrStringsOrFiles as $k=>&$v) {
            if (is_dir($v)) {
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
            }
        }

        parent::__construct($listOrStringsOrFiles);
    }

    public function offsetGet($offset)
    {
        if (!parent::offsetExists($offset)) {
            throw new \InvalidArgumentException("Item with id '$offset' could not be found.");
        }

        $v = parent::offsetGet($offset);
        if ($v instanceof \SplFileInfo) {
            return file_get_contents($v);
        }

        return $v;
    }

}
