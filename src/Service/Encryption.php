<?php

namespace App\Service;

use App\Entity\Paste;
use App\Entity\User;
use App\Exception\EncryptionNotAvailableException;
use Exception;
use SodiumException;

class Encryption
{

    const OPENSSL_CIPHER = "aes-256-cbc";
    const OPENSSL_DIGEST = "sha512";

    /**
     * @throws EncryptionNotAvailableException
     * @throws SodiumException
     * @throws Exception
     */
    public function generateEncryptionKey($plaintextPassword)
    {
        if (!function_exists("sodium_crypto_secretbox")) {
            throw new EncryptionNotAvailableException();
        }
        $generated_key = sodium_crypto_secretbox_keygen();
        $encryptionKeyNonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $passwordNonce = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);

        $keyDerivedFromPassword = sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            $plaintextPassword,
            $passwordNonce,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );
        $encryptedEncryptionKey = sodium_crypto_secretbox($generated_key, $encryptionKeyNonce, $keyDerivedFromPassword);

        return [
            sodium_bin2hex($passwordNonce),
            sodium_bin2hex($encryptionKeyNonce),
            sodium_bin2hex($encryptedEncryptionKey)
        ];
    }

    /**
     * @throws EncryptionNotAvailableException
     * @throws SodiumException
     */
    public function decryptEncryptionKey(User $user, string $plaintextPassword)
    {
        if (!function_exists("sodium_crypto_secretbox")) {
            throw new EncryptionNotAvailableException();
        }

        $keyDerivedFromPassword = sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            $plaintextPassword,
            sodium_hex2bin($user->getPasswordNonce()),
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );

        return sodium_crypto_secretbox_open(
            sodium_hex2bin($user->getEncryptedEncryptionKey()),
            sodium_hex2bin($user->getEncryptionKeyNonce()),
            $keyDerivedFromPassword
        );
    }

    /**
     * @throws EncryptionNotAvailableException
     * @throws SodiumException
     * @throws Exception
     */
    public function encrypt(Paste $paste, string $decryptedEncryptionKey): Paste
    {
        if (!function_exists("sodium_crypto_secretbox")) {
            throw new EncryptionNotAvailableException();
        }
        $pasteNonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $paste->setNonce(sodium_bin2hex($pasteNonce));
        $encryptedContent = sodium_crypto_secretbox($paste->getContent(), $pasteNonce, $decryptedEncryptionKey);
        $encryptedContent = sodium_bin2hex($encryptedContent);

        $paste->setContent($encryptedContent);
        return $paste;
    }


    /**
     * @throws EncryptionNotAvailableException
     * @throws SodiumException
     */
    public function decrypt(Paste $paste, string $decryptedEncryptionKey)
    {
        if (!function_exists("sodium_crypto_secretbox")) {
            throw new EncryptionNotAvailableException();
        }
        $decryptedContent = sodium_crypto_secretbox_open(
            sodium_hex2bin($paste->getContent()),
            sodium_hex2bin($paste->getNonce()),
            $decryptedEncryptionKey
        );
        return $decryptedContent;
    }

}