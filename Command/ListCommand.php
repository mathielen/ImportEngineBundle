<?php

namespace Mathielen\ImportEngineBundle\Command;

use Mathielen\ImportEngine\Importer\ImporterRepository;
use Mathielen\ImportEngine\Validation\DummyValidation;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends Command
{

    /**
     * @var ImporterRepository
     */
    private $importerRepository;

    public function __construct(ImporterRepository $importerRepository)
    {
        parent::__construct('importengine:list');

        $this->importerRepository = $importerRepository;
    }

    protected function configure()
    {
        $this->setDescription('Lists all available importer');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $table = new Table($output);
        $table->setHeaders(['id', 'auto-detectable', 'validation']);

        foreach ($this->importerRepository->getIds() as $importerId) {
            $importer = $this->importerRepository->get($importerId);

            $table->addRow([
                $importerId,
                $this->importerRepository->hasPrecondition($importerId) ? 'Yes' : 'No',
                ($importer->validation() instanceof DummyValidation) ? 'No' : 'Yes',
            ]);
        }

        $table->render();
    }
}
