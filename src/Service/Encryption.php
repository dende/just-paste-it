<?php

namespace App\Service;

use App\Entity\Paste;
use App\Entity\User;
use App\Exception\EncryptionNotAvailableException;
use Exception;
use SodiumException;

class Encryption {

    const OPENSSL_CIPHER = "aes-256-cbc";
    const OPENSSL_DIGEST = "sha512";

    /**
     * @throws EncryptionNotAvailableException
     * @throws SodiumException
     * @throws Exception
     */
    public function generateEncryptionKey($plaintextPassword) {
        if (function_exists("sodium_crypto_secretbox")) {
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

        } else {
            if (!function_exists("openssl_get_cipher_methods")){
                throw new EncryptionNotAvailableException("Can't use openssl");
            }
            $availableCiphers = openssl_get_cipher_methods();
            if (!in_array(self::OPENSSL_CIPHER, $availableCiphers)) {
                throw new EncryptionNotAvailableException("Openssl can't use aes-256-cbc");
            }

            $passwordNonce = random_bytes(12);
            $generated_key = random_bytes(32);

            $keyDerivedFromPassword = openssl_pbkdf2(
                $plaintextPassword,
                $passwordNonce,
                32,
                10000,
                self::OPENSSL_DIGEST
            );

            $iv_len = openssl_cipher_iv_length(self::OPENSSL_CIPHER);
            $encryptionKeyNonce = random_bytes($iv_len);

            $encryptedEncryptionKey = openssl_encrypt(
                $keyDerivedFromPassword,
                self::OPENSSL_CIPHER,
                $generated_key,
                0,
                $encryptionKeyNonce,
            );
        }
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
    public function decryptEncryptionKey(User $user, string $plaintextPassword) {
        if (function_exists("sodium_crypto_secretbox")) {

            $keyDerivedFromPassword = sodium_crypto_pwhash(
                SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
                $plaintextPassword,
                sodium_hex2bin($user->getPasswordNonce()),
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE,
                SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
            );

            $decryptedEncryptionKey = sodium_crypto_secretbox_open(
                sodium_hex2bin($user->getEncryptedEncryptionKey()),
                sodium_hex2bin($user->getEncryptionKeyNonce()),
                $keyDerivedFromPassword
            );

        } else {
            if (!function_exists("openssl_get_cipher_methods")){
                throw new EncryptionNotAvailableException("Can't use openssl");
            }
            $availableCiphers = openssl_get_cipher_methods();
            if (!in_array(self::OPENSSL_CIPHER, $availableCiphers)) {
                throw new EncryptionNotAvailableException("Openssl can't use aes-256-cbc");
            }


            $keyDerivedFromPassword = openssl_pbkdf2(
                $plaintextPassword,
                $user->getPasswordNonce(),
                32,
                10000,
                self::OPENSSL_DIGEST
            );

            $decryptedEncryptionKey = openssl_decrypt(
                $user->getEncryptedEncryptionKey(),
                self::OPENSSL_CIPHER,
                $keyDerivedFromPassword,
                0,
                $user->getEncryptionKeyNonce()
            );
        }
        return $decryptedEncryptionKey;
    }

    /**
     * @throws EncryptionNotAvailableException
     * @throws SodiumException
     * @throws Exception
     */
    public function encrypt(Paste $paste, string $decryptedEncryptionKey) : Paste {
        if (function_exists("sodium_crypto_secretbox")) {
            $pasteNonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $paste->setNonce(sodium_bin2hex($pasteNonce));
            $encryptedContent = sodium_crypto_secretbox($paste->getContent(), $pasteNonce, $decryptedEncryptionKey);
            $encryptedContent = sodium_bin2hex($encryptedContent);
        } else {
            if (!function_exists("openssl_get_cipher_methods")){
                throw new EncryptionNotAvailableException("Can't use openssl");
            }

            $availableCiphers = openssl_get_cipher_methods();
            if (!in_array(self::OPENSSL_CIPHER, $availableCiphers)) {
                throw new EncryptionNotAvailableException("Openssl can't use aes-256-cbc");
            }

            $iv_len = openssl_cipher_iv_length(self::OPENSSL_CIPHER);
            $pasteNonce = random_bytes($iv_len);
            $paste->setNonce($pasteNonce);

            $encryptedContent = openssl_encrypt(
                $paste->getContent(),
                self::OPENSSL_CIPHER,
                $decryptedEncryptionKey,
                0,
                $paste->getNonce()
            );

        }

        $paste->setContent($encryptedContent);
        return $paste;
    }


    public function decrypt(Paste $paste, string $decryptedEncryptionKey)
    {
        if (function_exists("sodium_crypto_secretbox")) {
            $decryptedContent = sodium_crypto_secretbox_open(
                sodium_hex2bin($paste->getContent()),
                sodium_hex2bin($paste->getNonce()),
                $decryptedEncryptionKey
            );
        } else {
            if (!function_exists("openssl_get_cipher_methods")){
                throw new EncryptionNotAvailableException("Can't use openssl");
            }

            $availableCiphers = openssl_get_cipher_methods();
            if (!in_array(self::OPENSSL_CIPHER, $availableCiphers)) {
                throw new EncryptionNotAvailableException("Openssl can't use aes-256-cbc");
            }

            $decryptedContent = openssl_decrypt(
                $paste->getContent(),
                self::OPENSSL_CIPHER,
                $decryptedEncryptionKey,
                0,
                $paste->getNonce()
            );

        }

        return $decryptedContent;
    }

}