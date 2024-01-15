<?php

namespace App\Controller;

use App\Entity\Attachment;
use App\Entity\Paste;
use App\Entity\User;
use App\Repository\AttachmentRepository;
use App\Repository\PasteRepository;
use App\Service\Encryption;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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

        return $this->render(
            'paste/index.html.twig',
            ['user' => $user],
        );
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
    public function store(Request $request, #[CurrentUser] ?User $user, PasteRepository $pasteRepository, EntityManagerInterface $entityManager, LoggerInterface $logger): Response
    {
        $session = $request->getSession();
        $decryptedEncryptionKey = $session->get('decryptedEncryptionKey');

        $paste = new Paste();

        $content = $request->request->get('content');
        $url = $request->request->get('url');

        if (empty($content)) {
            $content = "";
        }

        if ($url) {
            $existingPaste = $pasteRepository->findByUrlAndUser($url, $user);
            if ($existingPaste) {
                $url = $pasteRepository->getRandomUrl();
            }
        } else {
            $url = $pasteRepository->getRandomUrl();
        }

        try {
            $TTL = \DateInterval::createFromDateString($request->request->get('TTL'));
            $paste->setTTL($TTL);
        } catch (\ErrorException) {
            $logger->warning("someone tried to submit {$request->request->get('TTL')} as a datetimeinterval");
        }


        $paste->setUrl($url);
        $paste->setContent($content);
        $paste = $this->encryption->encryptPaste($paste, $decryptedEncryptionKey);

        $paste->setUser($user);
        $paste->setPublic(false);

        $entityManager->persist($paste);
        $entityManager->persist($user);
        // actually executes the queries (i.e. the INSERT query)

        /* @var UploadedFile[] $files */
        $files = $request->files->get('files');

        foreach ($files as $file) {
            $attachment = new Attachment();
            $attachment->setPaste($paste);
            $attachment->setFilename($file->getClientOriginalName());
            $attachment->setMimetype($file->getMimeType());
            $attachment->setContent($this->encryption->encryptFile($file, $paste, $decryptedEncryptionKey));
            $entityManager->persist($attachment);
            $paste->addAttachment($attachment);
        }

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
        $decryptedContent = $this->encryption->decryptPaste($paste, $decryptedEncryptionKey);
        $paste->setContent($decryptedContent);

        $attachments = $paste->getAttachments();


        $created = \DateTimeImmutable::createFromMutable($paste->getCreated());

        $then = $created->add($paste->getTTL());

        return $this->render('paste/show.html.twig',
            [
                "paste" => $paste,
                "then" => $then,
                "attachments" => $attachments
            ]);
    }

    #[Route('/delete/{url}', name: 'paste.delete', methods: ['GET'])]
    public function delete(string $url, #[CurrentUser] ?User $user, PasteRepository $pasteRepository, EntityManagerInterface $entityManager, Request $request): Response
    {
        $paste = $pasteRepository->findByUrlAndUser($url, $user);
        if (empty($paste)) {
            return $this->redirectToRoute("paste.index");
        }


        $entityManager->remove($paste);
        $entityManager->flush();

        return $this->redirectToRoute("paste.index");
    }


    #[Route('/{url}/{attachmentId}', name: 'paste.attachment.download', methods: ['GET'])]
    public function download(string $url, int $attachmentId, #[CurrentUser] ?User $user, PasteRepository $pasteRepository, AttachmentRepository $attachmentRepository, Request $request): Response
    {
        $paste = $pasteRepository->findByUrlAndUser($url, $user);
        if (empty($paste)) {
            return $this->redirectToRoute("paste.index");
        }

        $session = $request->getSession();
        $decryptedEncryptionKey = $session->get('decryptedEncryptionKey');

        $attachments = $paste->getAttachments();

        foreach ($attachments as $attachment) {
            if ($attachment->getId() == $attachmentId) {
                $decryptedContent = $this->encryption->decryptFile($attachment, $paste, $decryptedEncryptionKey);
                $response = new Response();
                $response->headers->set('Content-Type', $attachment->getMimetype());
                $response->headers->set('Content-Disposition', 'attachment;filename="' . $attachment->getFilename());
                $response->setContent($decryptedContent);
                return $response;
            }
        }

        $response = new Response();
        $response->setContent("404 Attachment not found");
        return $response;

    }

}
