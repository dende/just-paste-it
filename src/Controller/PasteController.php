<?php

namespace App\Controller;

use App\Entity\Paste;
use App\Entity\User;
use App\Repository\PasteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[IsGranted('ROLE_USER')]
class PasteController extends AbstractController
{
    #[Route('/', name: 'paste', methods: ['GET'])]
    public function index(#[CurrentUser] ?User $user): Response
    {
        return $this->render(
            'paste/index.html.twig',
            ['user' => $user],
        );
    }

    #[Route('/', name: 'create_paste', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] ?User $user, PasteRepository $pasteRepository, EntityManagerInterface $entityManager): Response
    {
        $content = $request->request->get('content');
        $url = $request->request->get('url');
        if (empty($content)) {
            if (!empty($url)) {
                return $this->redirectToRoute("show_paste", ['url' => $url]);
            }
            return $this->redirectToRoute("paste");
        }

        if ($url) {
            $paste = $pasteRepository->findByUrl($url);
            if ($paste) {
                $url = $pasteRepository->getRandomUrl();
            }
        } else {
            $url = $pasteRepository->getRandomUrl();
        }

        $paste = new Paste();
        $paste->setUrl($url);
        $paste->setContent($content);

        $paste->setUser($user);

        $entityManager->persist($paste);
        $entityManager->persist($user);
        // actually executes the queries (i.e. the INSERT query)
        $entityManager->flush();

        return $this->redirectToRoute("show_paste", ["url" => $paste->getUrl()]);
    }

    #[Route('/{url}', name: 'show_paste', methods: ['GET'])]
    public function show(string $url, #[CurrentUser] ?User $user, PasteRepository $pasteRepository, Request $request): Response
    {
        $paste = $pasteRepository->findByUrlAndUser($url, $user);
        if (empty($paste)) {
            return $this->redirectToRoute("paste");
        }

        $session = $request->getSession();
        $decryptedEncryptionKey = $session->get('decryptedEncryptionKey');
        $decryptedContent = \sodium_crypto_aead_aes256gcm_decrypt($paste->getContent(), null, $paste->getNonce(), $decryptedEncryptionKey);
        $paste->setContent($decryptedContent);
        return $this->render('paste/show.html.twig',
            [
                "paste" => $paste
            ]);

    }

}
