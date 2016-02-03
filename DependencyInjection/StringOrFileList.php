<?php
namespace Mathielen\ImportEngineBundle\DependencyInjection;

use Symfony\Component\Finder\Iterator\RecursiveDirectoryIterator;

class StringOrFileList implements \ArrayAccess
{

    /**
     * @var array
     */
    private $listOrStringsOrFiles;

    public function __construct(array $listOrStringsOrFiles)
    {
        $this->listOrStringsOrFiles = $listOrStringsOrFiles;

        foreach ($this->listOrStringsOrFiles as $k=>&$v) {
            if (is_dir($v)) {
                $iterator = new RecursiveDirectoryIterator($v, \FilesystemIterator::KEY_AS_PATHNAME);
                $iterator = new \RecursiveIteratorIterator($iterator);

                /** @var \Symfony\Component\Finder\SplFileInfo $file */
                foreach ($iterator as $file) {
                    if (is_file($file)) {
                        $this->listOrStringsOrFiles[$file->getRelativePathname()] = $file;
                    }
                }

                unset($this->listOrStringsOrFiles[$k]);
            } elseif (is_file($v)) {
                $v = new \SplFileInfo($v);
            }
        }
    }

    public function offsetExists($offset)
    {
        return isset($listOrStringsOrFiles[$offset]);
    }

    public function offsetGet($offset)
    {
        $v = $this->listOrStringsOrFiles[$offset];
        if ($v instanceof \SplFileInfo) {
            return file_get_contents($v);
        }

        return $v;
    }

    public function offsetSet($offset, $value)
    {
        // TODO: Implement offsetSet() method.
    }

    public function offsetUnset($offset)
    {
        // TODO: Implement offsetUnset() method.
    }

}
