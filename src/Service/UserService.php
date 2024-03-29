<?php

namespace App\Service;

use App\Entity\User;
use App\Exception\EncryptionNotAvailableException;
use App\Exception\UserCreationFailedException;
use App\Repository\UserRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\Exception\ORMException;
use SodiumException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService {

    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserRepository $userRepository, Encryption $encryption, UserPasswordHasherInterface $passwordHasher)
    {
        $this->encryption = $encryption;
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
    }

    /**
     * @throws SodiumException
     * @throws UserCreationFailedException
     * @throws ORMException
     * @throws EncryptionNotAvailableException
     * @throws \Exception
     */
    public function createUser(string $username, string $plaintextPassword)
    {

        list($passwordNonce, $encryptionKeyNonce, $encryptedEncryptionKey) = $this->encryption->generateEncryptionKey($plaintextPassword);

        $user = new User();
        $user->setUsername($username);
        $user->setEncryptionKeyNonce($encryptionKeyNonce);
        $user->setPasswordNonce($passwordNonce);
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
            throw new UserCreationFailedException();
        }

    }

    public function changePassword(User $user, string $old_password, string $new_password, string $new_password_repeat)
    {

        if (!$this->passwordHasher->isPasswordValid(
            $user,
            $old_password
        )){
            return "old pw is wrong";
            // todo passwords dont match
        }

        if ($new_password_repeat != $new_password) {
            return "new pws dont match";
            // todo new passwords dont match
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $new_password));


        $encryptedEncryptionKey = $this->encryption->reEncryptEncryptionKey($user, $old_password, $new_password);

        $user->setEncryptedEncryptionKey($encryptedEncryptionKey);




        try {
            $this->userRepository->add($user);
        } catch (OptimisticLockException $e) {
            throw new UserCreationFailedException();
        }

        return true;

    }

}