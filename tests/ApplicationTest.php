<?php

namespace App\Tests;

use App\Entity\Paste;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ApplicationTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $databasePath;
    private mixed $previousDatabaseUrl;

    protected function setUp(): void
    {
        $this->databasePath = tempnam(sys_get_temp_dir(), 'paste-test-');
        $this->previousDatabaseUrl = $_ENV['DATABASE_URL'] ?? null;
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///'.$this->databasePath;
        $this->client = self::createClient();
        $this->client->catchExceptions(false);

        // Exercise the actual migration history against an isolated database.
        $application = new Application(self::$kernel);
        $application->setAutoExit(false);
        $input = new ArrayInput(['command' => 'doctrine:migrations:migrate', '--no-interaction' => true]);
        $input->setInteractive(false);
        $output = new BufferedOutput();
        self::assertSame(0, $application->run($input, $output), $output->fetch());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->previousDatabaseUrl === null) {
            unset($_ENV['DATABASE_URL'], $_SERVER['DATABASE_URL']);
        } else {
            $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $this->previousDatabaseUrl;
        }
        unlink($this->databasePath);
    }

    public function testAnonymousAccessAndCsrfProtection(): void
    {
        $this->client->catchExceptions(true);
        $this->client->request('GET', '/');
        self::assertResponseRedirects('http://localhost/login');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="_csrf_token"]');

        $this->client->request('POST', '/login', [
            '_username' => 'someone', '_password' => 'invalid', '_csrf_token' => 'invalid',
        ]);
        self::assertResponseRedirects('http://localhost/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.error-content', 'Invalid CSRF token');
    }

    public function testEncryptedPasteAndAttachmentSurvivePasswordChange(): void
    {
        self::getContainer()->get(UserService::class)->createUser('alice', 'original-password');
        $this->login('original-password');
        self::assertSelectorTextContains('h1', 'alice');

        $uploadPath = tempnam(sys_get_temp_dir(), 'paste-upload-');
        file_put_contents($uploadPath, 'Private attachment contents');
        try {
            $this->client->request('POST', '/', [
                'url' => 'upgrade-test', 'content' => 'Private paste contents', 'TTL' => '2 weeks',
            ], ['files' => [new UploadedFile($uploadPath, 'example.txt', 'text/plain', null, true)]]);
        } finally {
            if (is_file($uploadPath)) {
                unlink($uploadPath);
            }
        }
        self::assertResponseRedirects('/upgrade-test');

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $paste = $em->getRepository(Paste::class)->findOneBy(['url' => 'upgrade-test']);
        self::assertNotNull($paste);
        self::assertNotSame('Private paste contents', $paste->getContent());
        $attachmentId = $paste->getAttachments()->first()->getId();
        $storedAttachment = $em->getConnection()->fetchOne('SELECT content FROM attachment WHERE id = ?', [$attachmentId]);
        self::assertNotSame('Private attachment contents', $storedAttachment);

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('textarea', 'Private paste contents');
        $this->client->request('GET', '/upgrade-test/'.$attachmentId);
        self::assertResponseIsSuccessful();
        self::assertSame('Private attachment contents', $this->client->getResponse()->getContent());

        $this->client->request('POST', '/changepw', [
            'password' => 'original-password', 'new_password' => 'changed-password',
            'new_password_repeat' => 'changed-password',
        ]);
        self::assertResponseRedirects();
        $this->client->request('GET', '/logout');
        $this->login('changed-password');
        $this->client->request('GET', '/upgrade-test');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('textarea', 'Private paste contents');
        $this->client->request('GET', '/upgrade-test/'.$attachmentId);
        self::assertSame('Private attachment contents', $this->client->getResponse()->getContent());

        $this->client->request('GET', '/delete/upgrade-test');
        self::assertResponseRedirects('/');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame(0, $em->getRepository(Paste::class)->count([]));
        self::assertSame(0, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM attachment'));
    }

    private function login(string $password): void
    {
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('login')->form([
            '_username' => 'alice', '_password' => $password,
        ]));
        self::assertResponseRedirects('http://localhost/');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }
}
