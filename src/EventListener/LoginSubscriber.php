<?php


namespace App\EventListener;

use App\Service\Encryption;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use App\Entity\User;

class LoginSubscriber implements EventSubscriberInterface
{
    private Encryption $encryption;

    public function __construct(Encryption $encryption)
    {
        $this->encryption =  $encryption;
    }

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

        $decryptedEncryptionKey = $this->encryption->decryptEncryptionkey($user, $plaintextPassword);

        $session = $event->getRequest()->getSession();
        $session->set('decryptedEncryptionKey', $decryptedEncryptionKey);
    }
}
