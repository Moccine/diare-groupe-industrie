<?php

namespace App\Tests\Controller;

use App\Entity\ContactRequest;
use App\Entity\SiteSettings;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;

final class ContactRequestTest extends WebTestCase
{
    use MailerAssertionsTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite est requis pour le formulaire de contact.');
        }

        $databaseUrl = 'sqlite:///'.dirname(__DIR__, 2).'/var/contact_phpunit.db';
        putenv('APP_ENV=test');
        putenv('DATABASE_URL='.$databaseUrl);
        putenv('RECAPTCHA_ENABLED=0');
        $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $databaseUrl;
        $_SERVER['RECAPTCHA_ENABLED'] = $_ENV['RECAPTCHA_ENABLED'] = '0';

        $this->client = static::createClient();
        $manager = $this->manager();
        $params = $manager->getConnection()->getParams();
        if (($params['driver'] ?? '') !== 'pdo_sqlite') {
            self::markTestSkipped('Le test contact refuse de s’exécuter hors SQLite.');
        }

        $tool = new SchemaTool($manager);
        $metadata = $manager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        $this->clearRateLimiter();
    }

    public function testValidContactPersistsAndSendsBothEmails(): void
    {
        $this->configureSiteEmail('contact@diare.example');

        $this->submitContact();

        self::assertResponseRedirects('/contact');
        self::assertSame(1, $this->countContacts());
        self::assertEmailCount(2);

        $staff = $this->findEmail('contact@diare.example');
        $visitor = $this->findEmail('amina@example.com');

        self::assertEmailAddressContains($staff, 'From', 'noreply@diaregroupe.local');
        self::assertEmailAddressContains($staff, 'Reply-To', 'amina@example.com');
        self::assertEmailSubjectContains($staff, 'Nouveau message reçu');
        self::assertEmailHtmlBodyContains($staff, 'Nouveau message reçu');
        self::assertEmailHtmlBodyContains($staff, 'Diaré Distribution');

        self::assertEmailAddressContains($visitor, 'To', 'amina@example.com');
        self::assertEmailAddressContains($visitor, 'Reply-To', 'contact@diare.example');
        self::assertEmailSubjectContains($visitor, 'Nous avons bien reçu votre message');
        self::assertEmailHtmlBodyContains($visitor, 'Bonjour Amina');
    }

    public function testInvalidEmailIsRejected(): void
    {
        $this->configureSiteEmail('contact@diare.example');
        $this->submitContact(['contact_request[email]' => 'pas-un-email']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->countContacts());
        self::assertEmailCount(0);
    }

    public function testEmptyRequiredFieldIsRejected(): void
    {
        $this->submitContact(['contact_request[lastName]' => '']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->countContacts());
    }

    public function testShortMessageIsRejected(): void
    {
        $this->submitContact(['contact_request[message]' => 'court']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->countContacts());
    }

    public function testMissingConsentIsRejected(): void
    {
        $crawler = $this->client->request('GET', '/contact');
        $form = $crawler->selectButton('Envoyer le message')->form($this->payload());
        $form['contact_request[consent]']->untick();
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Le consentement est obligatoire.', (string) $this->client->getResponse()->getContent());
        self::assertSame(0, $this->countContacts());
    }

    public function testHoneypotDoesNotPersist(): void
    {
        $this->configureSiteEmail('contact@diare.example');
        $crawler = $this->client->request('GET', '/contact');
        $form = $crawler->selectButton('Envoyer le message')->form($this->payload());
        $form['contact_request[website]'] = 'https://spam.example';
        $this->client->submit($form);

        self::assertResponseRedirects('/contact');
        self::assertSame(0, $this->countContacts());
        self::assertEmailCount(0);
    }

    public function testRateLimitBlocksTheSixthSubmission(): void
    {
        $this->configureSiteEmail('contact@diare.example');

        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $this->submitContact([
                'contact_request[email]' => 'visitor'.$attempt.'@example.com',
            ]);
            self::assertResponseRedirects('/contact');
        }

        $this->submitContact(['contact_request[email]' => 'blocked@example.com']);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Trop de messages', (string) $this->client->getResponse()->getContent());
        self::assertSame(5, $this->countContacts());
    }

    public function testInvalidCsrfDoesNotPersist(): void
    {
        $crawler = $this->client->request('GET', '/contact');
        $form = $crawler->selectButton('Envoyer le message')->form($this->payload());
        $form['contact_request[_token]'] = 'jeton-invalide';
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->countContacts());
        self::assertEmailCount(0);
    }

    public function testMailFailureKeepsTheRequest(): void
    {
        $this->configureSiteEmail('contact@diare.example');
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('SMTP indisponible'));
        static::getContainer()->set(MailerInterface::class, $mailer);

        $this->submitContact();

        self::assertResponseRedirects('/contact');
        self::assertSame(1, $this->countContacts());
    }

    public function testNoEmailIsSentWhenSiteEmailIsMissing(): void
    {
        $this->submitContact();

        self::assertResponseRedirects('/contact');
        self::assertSame(1, $this->countContacts());
        self::assertEmailCount(0);
    }

    public function testRejectedRecaptchaDoesNotPersist(): void
    {
        self::ensureKernelShutdown();
        putenv('RECAPTCHA_ENABLED=1');
        $_SERVER['RECAPTCHA_ENABLED'] = $_ENV['RECAPTCHA_ENABLED'] = '1';
        $_SERVER['RECAPTCHA_SITE_KEY'] = $_ENV['RECAPTCHA_SITE_KEY'] = '';
        $_SERVER['RECAPTCHA_SECRET_KEY'] = $_ENV['RECAPTCHA_SECRET_KEY'] = '';
        $this->client = static::createClient();
        $this->configureSiteEmail('contact@diare.example');

        $this->submitContact();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('La vérification anti-spam a échoué.', (string) $this->client->getResponse()->getContent());
        self::assertSame(0, $this->countContacts());
        self::assertEmailCount(0);
    }

    private function configureSiteEmail(string $email): void
    {
        $settings = (new SiteSettings())->setEmail($email)->setCompanyName('Diaré Groupe Industrie');
        $this->manager()->persist($settings);
        $this->manager()->flush();
    }

    /** @param array<string, string> $override */
    private function submitContact(array $override = []): void
    {
        $crawler = $this->client->request('GET', '/contact');
        $form = $crawler->selectButton('Envoyer le message')->form($this->payload($override));
        $this->client->submit($form);
    }

    /** @param array<string, string> $override
     * @return array<string, string>
     */
    private function payload(array $override = []): array
    {
        return $override + [
            'contact_request[lastName]' => 'Diallo',
            'contact_request[firstName]' => 'Amina',
            'contact_request[company]' => 'Diaré Distribution',
            'contact_request[email]' => 'amina@example.com',
            'contact_request[phone]' => '620000000',
            'contact_request[subject]' => 'Demande commerciale',
            'contact_request[message]' => 'Bonjour, nous souhaitons des informations sur vos produits.',
            'contact_request[consent]' => '1',
        ];
    }

    private function countContacts(): int
    {
        return $this->manager()->getRepository(ContactRequest::class)->count([]);
    }

    private function findEmail(string $recipient): \Symfony\Component\Mime\Email
    {
        foreach (self::getMailerMessages() as $message) {
            if (!$message instanceof \Symfony\Component\Mime\Email) {
                continue;
            }

            foreach ($message->getTo() as $address) {
                if ($address->getAddress() === $recipient) {
                    return $message;
                }
            }
        }

        self::fail('Aucun e-mail envoyé à '.$recipient.'.');
    }

    private function clearRateLimiter(): void
    {
        $cache = static::getContainer()->get('cache.rate_limiter');
        if ($cache instanceof CacheItemPoolInterface) {
            $cache->clear();
        }
    }

    private function manager(): EntityManagerInterface
    {
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }
}
