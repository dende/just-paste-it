<?php

namespace App\Controller;

use App\Entity\Paste;
use App\Repository\PasteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PasteController extends AbstractController
{
    #[Route('/', name: 'paste', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('paste/index.html.twig');
    }

    #[Route('/', name: 'create_paste', methods: ['POST'])]
    public function create(Request $request, PasteRepository $pasteRepository, EntityManagerInterface $entityManager): Response
    {
        $content = $request->request->get('content');

        if (empty($content)) {
            return $this->redirectToRoute("paste");
        }

        $urlExisted = false;
        $url = $request->request->get('url');
        if ($url) {
            $paste = $pasteRepository->findByUrl($url);
            if ($paste) {
                $urlExisted = true;
                $url = $pasteRepository->getRandomUrl();
            }
        } else {
            $url = $pasteRepository->getRandomUrl();
        }

        $paste = new Paste();
        $paste->setUrl($url);
        $paste->setContent($content);

        $entityManager->persist($paste);

        // actually executes the queries (i.e. the INSERT query)
        $entityManager->flush();

        return $this->redirectToRoute("show", ["url" => $paste->getUrl()]);
    }

    /**
     * @Route("/{url}", name="show")
     */
    public function show(string $url, PasteRepository $pasteRepository): Response
    {
        $paste = $pasteRepository->findByUrl($url);
        if (empty($paste)) {
            return $this->redirectToRoute("paste");
        }

        return $this->render('paste/show.html.twig',
            [
                "paste" => $paste
            ]);

    }

}
