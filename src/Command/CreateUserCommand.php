<?php

namespace App\Command;

use App\Entity\Paste;
use App\Entity\User;
use App\Repository\PasteRepository;
use App\Repository\UserRepository;
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

    private UserPasswordHasherInterface $passwordHasher;
    private UserRepository $userRepository;
    private PasteRepository $pasteRepository;

    public function __construct(UserPasswordHasherInterface $passwordHasher, UserRepository $userRepository, PasteRepository $pasteRepository)
    {
        $this->passwordHasher = $passwordHasher;
        $this->userRepository = $userRepository;
        $this->pasteRepository = $pasteRepository;
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

        $user = new User();

        $user->setUsername($username);

        $encryptionKeyNonce = \random_bytes(\SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES);
        $passwordNonce = \random_bytes(\SODIUM_CRYPTO_PWHASH_SALTBYTES);

        $user->setEncryptionKeyNonce($encryptionKeyNonce);
        $user->setPasswordNonce($passwordNonce);

        $generated_key = \sodium_crypto_aead_aes256gcm_keygen();

        $output->writeln("output");
        $output->writeln($generated_key);

        $keyDerivedFromPassword = sodium_crypto_pwhash(
            32,
            $plaintextPassword,
            $passwordNonce,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );

        $encryptedEncryptionKey = \sodium_crypto_aead_aes256gcm_encrypt($generated_key, null, $encryptionKeyNonce, $keyDerivedFromPassword);

        $user->setEncryptedEncryptionKey($encryptedEncryptionKey);

        // hash the password (based on the security.yaml config for the $user class)
        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            $plaintextPassword
        );

        $user->setPassword($hashedPassword);

        try {
            $this->userRepository->add($user);
        } catch (OptimisticLockException $e) {
            $output->writeln("Something went wrong while adding the user");
        }

        $plainTestContent = "this is a really nice text";
        $pasteNonce = \random_bytes(\SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES);
        $encryptedTestContent = \sodium_crypto_aead_aes256gcm_encrypt($plainTestContent, null, $pasteNonce, $generated_key);


        $paste = new Paste();
        $paste->setUser($user);
        $paste->setUrl("test");
        $paste->setContent($encryptedTestContent);
        $paste->setNonce($pasteNonce);

        try {
            $this->pasteRepository->add($paste);
        } catch (OptimisticLockException $e) {
            $output->writeln("Something went wrong while adding the paste");
        }

        return Command::SUCCESS;
    }
}
