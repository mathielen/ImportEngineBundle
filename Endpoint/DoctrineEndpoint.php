<?php

namespace Mathielen\ImportEngineBundle\Endpoint;

use Doctrine\Common\Persistence\ObjectManager;
use Mathielen\DataImport\Event\ImportProcessEvent;

class DoctrineEndpoint
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    private $chunkSize = null;
    private $currentChunkCount = 0;

    public function __construct(ObjectManager $objectManager, $chunkSize = null)
    {
        $this->objectManager = $objectManager;
        $this->chunkSize = $chunkSize;
    }

    public function add($entity)
    {
        $this->objectManager->persist($entity);
        ++$this->currentChunkCount;

        if ($this->chunkCompleted()) {
            $this->objectManager->flush();
            $this->currentChunkCount = 0;
        }
    }

    protected function chunkCompleted()
    {
        return $this->chunkSize && $this->currentChunkCount >= $this->chunkSize;
    }

    public function prepare(ImportProcessEvent $event)
    {
        $this->added = 0;
    }

    public function finish(ImportProcessEvent $event)
    {
        $this->objectManager->flush();
    }
    
    public function rollback()
    {
    }
    
}
