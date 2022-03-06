<?php

namespace App\Service;

use App\Entity\User;
use App\Exception\UserCreationFailedException;
use App\Repository\UserRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\Exception\ORMException;
use SodiumException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService {

    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher)
    {
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
    }

    /**
     * @throws SodiumException
     * @throws UserCreationFailedException
     * @throws ORMException
     */
    public function createUser(string $username, string $plaintextPassword)
    {

        if (\sodium_crypto_aead_aes256gcm_is_available()) {
            $encryptionKeyNonce = \random_bytes(\SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES);
            $passwordNonce = \random_bytes(\SODIUM_CRYPTO_PWHASH_SALTBYTES);
            $generated_key = \sodium_crypto_aead_aes256gcm_keygen();
            $keyDerivedFromPassword = sodium_crypto_pwhash(
                32,
                $plaintextPassword,
                $passwordNonce,
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE,
                SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
            );
            $encryptedEncryptionKey = \sodium_crypto_aead_aes256gcm_encrypt($generated_key, null, $encryptionKeyNonce, $keyDerivedFromPassword);

        } else {
            dd(openssl_get_cipher_methods());
        }



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
}