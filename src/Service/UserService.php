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

    public function __construct(UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher)
    {
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
            if (!function_exists("openssl_get_cipher_methods")){
                throw new EncryptionNotAvailableException("Can't use openssl");
            }
            $availableCiphers = openssl_get_cipher_methods();
            if (!in_array("aes-256-gcm", $availableCiphers)) {
                throw new EncryptionNotAvailableException("Openssl can't use aes-256-gcm");
            }
            $cipher_algo = "aes-256-gcm";
            $digest = "sha512";

            $passwordNonce = random_bytes(12);
            $generated_key = random_bytes(32);

            $keyDerivedFromPassword = openssl_pbkdf2(
                $plaintextPassword,
                $passwordNonce,
                32,
                10000,
                $digest
            );

            $iv_len = openssl_cipher_iv_length($cipher_algo);
            $encryptionKeyNonce = random_bytes($iv_len);

            openssl_encrypt(
                $keyDerivedFromPassword,
                $cipher_algo,
                $generated_key,
                0,
                $encryptionKeyNonce,
            );
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