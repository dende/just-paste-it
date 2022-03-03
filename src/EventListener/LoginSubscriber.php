<?php


namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use App\Entity\User;

class LoginSubscriber implements EventSubscriberInterface
{

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => [
                ['decryptEncryptionkey', 10],
            ],
        ];
    }

    public function decryptEncryptionkey(LoginSuccessEvent $event)
    {
        /** @var User $user */
        $user = $event->getUser();
        $plaintextPassword = $event->getRequest()->get('_password');

        $keyDerivedFromPassword = \sodium_crypto_pwhash(
            32,
            $plaintextPassword,
            $user->getPasswordNonce(),
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );


        $decryptedEncryptionKey = \sodium_crypto_aead_aes256gcm_decrypt($user->getEncryptedEncryptionKey(), null, $user->getEncryptionKeyNonce(), $keyDerivedFromPassword);

        $session = $event->getRequest()->getSession();
        $session->set('decryptedEncryptionKey', $decryptedEncryptionKey);
    }
}
