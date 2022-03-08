<?php

namespace App\Command;

use App\Entity\Paste;
use App\Entity\User;
use App\Repository\PasteRepository;
use App\Repository\UserRepository;
use App\Service\UserService;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Add a user',
)]
class CreateUserCommand extends Command
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;

        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');

        $question = new Question("Please enter the new username:\n");
        $username = $helper->ask($input, $output, $question);


        $question = new Question("Please enter the new password:\n");
        $question->setHidden(true);
        $question->setHiddenFallback(false);

        $plaintextPassword = $helper->ask($input, $output, $question);

        $this->userService->createUser($username, $plaintextPassword);

        return Command::SUCCESS;
    }
}
