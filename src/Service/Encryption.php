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
        if (sodium_crypto_aead_aes256gcm_is_available()) {
            $encryptionKeyNonce = random_bytes(\SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES);
            $passwordNonce = random_bytes(\SODIUM_CRYPTO_PWHASH_SALTBYTES);
            $generated_key = sodium_crypto_aead_aes256gcm_keygen();
            $keyDerivedFromPassword = sodium_crypto_pwhash(
                32,
                $plaintextPassword,
                $passwordNonce,
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE,
                SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
            );
            $encryptedEncryptionKey = sodium_crypto_aead_aes256gcm_encrypt($generated_key, null, $encryptionKeyNonce, $keyDerivedFromPassword);

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
        return [$passwordNonce, $encryptionKeyNonce, $encryptedEncryptionKey];
    }

    public function decryptEncryptionKey(User $user, string $plaintextPassword) {
        if (sodium_crypto_aead_aes256gcm_is_available()) {

            $keyDerivedFromPassword = sodium_crypto_pwhash(
                32,
                $plaintextPassword,
                $user->getPasswordNonce(),
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE,
                SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
            );


            $decryptedEncryptionKey = sodium_crypto_aead_aes256gcm_decrypt(
                $user->getEncryptedEncryptionKey(),
                null,
                $user->getEncryptionKeyNonce(),
                $keyDerivedFromPassword
            );

        } else {
            if (!function_exists("openssl_get_cipher_methods")){
                throw new EncryptionNotAvailableException("Can't use openssl");
            }
            $availableCiphers = openssl_get_cipher_methods();
            if (!in_array(self::OPENSSL_CIPHER, $availableCiphers)) {
                throw new EncryptionNotAvailableException("Openssl can't use aes-256-gcm");
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

    public function decrypt(Paste $paste, string $decryptedEncryptionKey)
    {
        if (sodium_crypto_aead_aes256gcm_is_available()) {
            $decryptedContent = \sodium_crypto_aead_aes256gcm_decrypt($paste->getContent(), null, $paste->getNonce(), $decryptedEncryptionKey);
        } else {
            if (!function_exists("openssl_get_cipher_methods")){
                throw new EncryptionNotAvailableException("Can't use openssl");
            }

            $availableCiphers = openssl_get_cipher_methods();
            if (!in_array(self::OPENSSL_CIPHER, $availableCiphers)) {
                throw new EncryptionNotAvailableException("Openssl can't use aes-256-gcm");
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

    public function encrypt(Paste $paste, string $decryptedEncryptionKey) : Paste {
        if (sodium_crypto_aead_aes256gcm_is_available()) {
            $pasteNonce = random_bytes(\SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES );
            $paste->setNonce($pasteNonce);
            $encryptedContent = \sodium_crypto_aead_aes256gcm_encrypt($paste->getContent(), null, $pasteNonce, $decryptedEncryptionKey);
        } else {
            if (!function_exists("openssl_get_cipher_methods")){
                throw new EncryptionNotAvailableException("Can't use openssl");
            }

            $availableCiphers = openssl_get_cipher_methods();
            if (!in_array("aes-256-gcm", $availableCiphers)) {
                throw new EncryptionNotAvailableException("Openssl can't use aes-256-gcm");
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
}