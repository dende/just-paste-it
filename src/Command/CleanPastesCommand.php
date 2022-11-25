<?php

namespace App\Command;

use App\Repository\PasteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clean-pastes',
    description: 'Deletes Pastes that are over their TTL',
)]
class CleanPastesCommand extends Command
{

    private PasteRepository $pasteRepository;
    private EntityManagerInterface $entityManager;


    public function __construct(PasteRepository $pasteRepository, EntityManagerInterface $entityManager)
    {
        $this->pasteRepository = $pasteRepository;
        $this->entityManager = $entityManager;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $deleted_pastes = 0;
        $io = new SymfonyStyle($input, $output);

        $pastes = $this->pasteRepository->findAll();

        $now = new \DateTimeImmutable();
        foreach ($pastes as $paste) {
            $then = $paste->getCreated()->add($paste->getTTL());
            if ($now > $then) {
                $this->entityManager->remove($paste);
                $this->entityManager->flush();
                $deleted_pastes++;
            }
        }

        if ($deleted_pastes) {
            $io->success("Deleted {$deleted_pastes} pastes.");
        } else {
            $io->success('No pastes have exceeded their TTL.');
        }

        return Command::SUCCESS;
    }
}
