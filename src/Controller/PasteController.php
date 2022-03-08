<?php

namespace App\Controller;

use App\Entity\Paste;
use App\Entity\User;
use App\Repository\PasteRepository;
use App\Service\Encryption;
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

    private Encryption $encryption;

    public function __construct(Encryption $encryption)
    {
        $this->encryption = $encryption;
    }

    #[Route('/', name: 'paste.index', methods: ['GET'])]
    public function index(#[CurrentUser] ?User $user): Response
    {
        return $this->redirectToRoute('paste.create');
    }

    #[Route('/create', name: 'paste.create', methods: ['GET'])]
    public function create(#[CurrentUser] ?User $user): Response
    {
        return $this->render(
            'paste/create.html.twig',
            ['user' => $user],
        );
    }

    #[Route('/', name: 'paste.store', methods: ['POST'])]
    public function store(Request $request, #[CurrentUser] ?User $user, PasteRepository $pasteRepository, EntityManagerInterface $entityManager): Response
    {
        $content = $request->request->get('content');
        $url = $request->request->get('url');
        if (empty($content)) {
            if (!empty($url)) {
                return $this->redirectToRoute("paste.show", ['url' => $url]);
            }
            return $this->redirectToRoute("paste.index");
        }

        if ($url) {
            $paste = $pasteRepository->findByUrl($url);
            if ($paste) {
                $url = $pasteRepository->getRandomUrl();
            }
        } else {
            $url = $pasteRepository->getRandomUrl();
        }

        $session = $request->getSession();
        $decryptedEncryptionKey = $session->get('decryptedEncryptionKey');


        $paste = new Paste();
        $paste->setUrl($url);
        $paste->setContent($content);
        $paste = $this->encryption->encrypt($paste, $decryptedEncryptionKey);

        $paste->setUser($user);
        $paste->setPublic(false);

        $entityManager->persist($paste);
        $entityManager->persist($user);
        // actually executes the queries (i.e. the INSERT query)
        $entityManager->flush();

        return $this->redirectToRoute("paste.show", ["url" => $paste->getUrl()]);
    }

    #[Route('/{url}', name: 'paste.show', methods: ['GET'])]
    public function show(string $url, #[CurrentUser] ?User $user, PasteRepository $pasteRepository, Request $request): Response
    {
        $paste = $pasteRepository->findByUrlAndUser($url, $user);
        if (empty($paste)) {
            return $this->redirectToRoute("paste.index");
        }

        $session = $request->getSession();
        $decryptedEncryptionKey = $session->get('decryptedEncryptionKey');
        $decryptedContent = $this->encryption->decrypt($paste, $decryptedEncryptionKey);
        $paste->setContent($decryptedContent);
        return $this->render('paste/show.html.twig',
            [
                "paste" => $paste
            ]);

    }

}
