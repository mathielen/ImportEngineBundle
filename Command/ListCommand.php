<?php
namespace Mathielen\ImportEngineBundle\Command;

use Mathielen\ImportEngine\Importer\ImporterRepository;
use Mathielen\ImportEngine\Validation\DummyValidation;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this->setName('importengine:list')
            ->setDescription('Lists all available importer')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $table = new Table($output);
        $table->setHeaders(['id', 'auto-detectable', 'validation']);

        /** @var ImporterRepository $importRepository */
        $importRepository = $this->getContainer()->get('mathielen_importengine.importer.repository');

        foreach ($importRepository->getIds() as $importerId) {
            $importer = $importRepository->get($importerId);

            $table->addRow([
                $importerId,
                $importRepository->hasPrecondition($importerId)?'Yes':'No',
                ($importer->validation() instanceof DummyValidation)?'No':'Yes'
            ]);
        }

        $table->render();
    }

}
