<?php

namespace App\Tests\Controller;

use App\Controller\Admin\Crud\JobApplicationCrudController;
use App\Entity\JobApplication;
use App\Entity\JobOffer;
use App\Entity\SiteSettings;
use App\Entity\User;
use App\Enum\ContractType;
use App\Enum\JobApplicationStatus;
use App\Service\AdminLinkFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class JobApplicationFlowTest extends WebTestCase
{
    use MailerAssertionsTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite est requis pour les candidatures.');
        }

        $databaseUrl = 'sqlite:///'.dirname(__DIR__, 2).'/var/job_application_phpunit.db';
        putenv('APP_ENV=test');
        putenv('DATABASE_URL='.$databaseUrl);
        putenv('RECAPTCHA_ENABLED=0');
        putenv('JOB_APPLICATION_NOTIFY_EMAIL=');
        putenv('JOB_APPLICATION_RETENTION_MONTHS=');
        $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $databaseUrl;
        $_SERVER['RECAPTCHA_ENABLED'] = $_ENV['RECAPTCHA_ENABLED'] = '0';
        $_SERVER['JOB_APPLICATION_NOTIFY_EMAIL'] = $_ENV['JOB_APPLICATION_NOTIFY_EMAIL'] = '';
        $_SERVER['JOB_APPLICATION_RETENTION_MONTHS'] = $_ENV['JOB_APPLICATION_RETENTION_MONTHS'] = '';

        $this->client = static::createClient();
        $manager = $this->manager();
        $params = $manager->getConnection()->getParams();
        if (($params['driver'] ?? '') !== 'pdo_sqlite') {
            self::markTestSkipped('Le test candidature refuse de s’exécuter hors SQLite.');
        }

        if (str_contains($manager->getConnection()->getDatabasePlatform()::class, 'SQLite')) {
            $manager->getConnection()->executeStatement('PRAGMA foreign_keys = ON');
        }

        $tool = new SchemaTool($manager);
        $metadata = $manager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        $this->clearRateLimiter();
    }

    public function testOpenOfferAcceptsAnApplicationAndNotifiesWithoutAttachingTheCv(): void
    {
        $this->configureSite('contact@diare.example');
        $offer = $this->persistOffer($this->openOffer('Conducteur de ligne', 'conducteur-de-ligne')->setApplicationEmail('rh-poste@example.com'));

        $this->submit('/nous-rejoindre/conducteur-de-ligne/postuler');

        self::assertResponseRedirects('/nous-rejoindre/conducteur-de-ligne/postuler/confirmation');
        self::assertEmailCount(2);
        $staff = $this->emailTo('rh-poste@example.com');
        $candidate = $this->emailTo('amina@example.com');
        self::assertEmailSubjectContains($staff, '[DGI] Nouvelle candidature — Conducteur de ligne');
        self::assertSame('Diaré Groupe Industrie', $staff->getFrom()[0]->getName());
        self::assertEmailAddressContains($staff, 'Reply-To', 'amina@example.com');
        self::assertEmailHtmlBodyContains($staff, 'Amina Diallo');
        self::assertEmailHtmlBodyContains($staff, 'Consulter la candidature');
        self::assertEmailHtmlBodyContains($staff, 'Conducteur de ligne');
        self::assertCount(0, $staff->getAttachments());
        self::assertStringNotContainsString('job-applications', (string) $staff->getHtmlBody());
        self::assertStringNotContainsString('note interne', (string) $staff->getHtmlBody());
        self::assertStringNotContainsString('note interne', (string) $staff->getTextBody());
        self::assertEmailSubjectContains($candidate, 'Confirmation de votre candidature — Diaré Groupe Industrie');
        self::assertEmailAddressContains($candidate, 'Reply-To', 'rh-poste@example.com');
        self::assertEmailHtmlBodyContains($candidate, 'Bonjour Amina');
        self::assertEmailHtmlBodyContains($candidate, 'au poste de « Conducteur de ligne »');
        self::assertEmailTextBodyContains($candidate, 'au poste de « Conducteur de ligne »');
        self::assertCount(0, $candidate->getAttachments());
        self::assertStringNotContainsString('/administration', (string) $candidate->getHtmlBody());

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Amina', (string) $this->client->getResponse()->getContent());
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');

        $application = $this->latest();
        self::assertSame($offer->getId(), $application->getJobOffer()?->getId());
        self::assertSame('Conducteur de ligne', $application->getJobTitleSnapshot());
        self::assertSame('Dubréka', $application->getLocationSnapshot());
        self::assertSame(JobApplicationStatus::New, $application->getStatus());
        self::assertSame(JobApplication::CONSENT_VERSION, $application->getConsentVersion());
        self::assertNull($application->getProcessedAt());
        self::assertFalse($application->isSpontaneous());
        self::assertNotNull($application->getCreatedAt());

        $path = dirname(__DIR__, 2).'/var/private/job-applications/'.$application->getCvStoredFilename();
        self::assertFileExists($path);
        self::assertStringNotContainsString('/public/', $path);
    }

    public function testExternalApplicationLinkIsPreserved(): void
    {
        $this->persistOffer($this->openOffer('Commercial', 'commercial')->setApplicationUrl('https://example.com/jobs/commercial'));

        $this->client->request('GET', '/nous-rejoindre/commercial');
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('https://example.com/jobs/commercial', $content);
        self::assertStringContainsString('Postuler à cette offre', $content);
        self::assertStringNotContainsString('/nous-rejoindre/commercial/postuler', $content);

        $this->client->request('POST', '/nous-rejoindre/commercial/postuler');
        self::assertResponseRedirects('https://example.com/jobs/commercial');
        self::assertSame(0, $this->countApplications());
    }

    public function testInternalOfferShowsTheApplicationForm(): void
    {
        $this->persistOffer($this->openOffer('Magasinier', 'magasinier'));

        $this->client->request('GET', '/nous-rejoindre/magasinier');
        self::assertStringContainsString('/nous-rejoindre/magasinier/postuler', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/nous-rejoindre/magasinier/postuler');
        $form = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('name="job_application[cv]"', $form);
        self::assertStringContainsString('pattern="[+0-9][0-9\\s\\(\\)\\.\\/\\-]{6,39}"', $form);

        $this->client->request('GET', '/nous-rejoindre');
        self::assertStringContainsString('/nous-rejoindre/candidature-spontanee', (string) $this->client->getResponse()->getContent());
    }

    public function testExpiredFutureAndUnpublishedOffersRejectApplications(): void
    {
        $this->persistOffer($this->openOffer('Expirée', 'expiree')->setExpiresAt(new \DateTimeImmutable('-1 hour')));
        $this->persistOffer($this->openOffer('Future', 'future')->setPublishedAt(new \DateTimeImmutable('+2 days')));
        $this->persistOffer($this->openOffer('Brouillon', 'brouillon')->setIsPublished(false));

        foreach (['expiree', 'future', 'brouillon', 'inconnue'] as $slug) {
            $this->client->request('GET', '/nous-rejoindre/'.$slug.'/postuler');
            self::assertResponseStatusCodeSame(404);
            self::assertStringContainsString('n’accepte plus', (string) $this->client->getResponse()->getContent());
            $this->client->request('POST', '/nous-rejoindre/'.$slug.'/postuler');
            self::assertResponseStatusCodeSame(404);
        }

        self::assertSame(0, $this->countApplications());
    }

    public function testSpontaneousApplicationDoesNotNeedAnOffer(): void
    {
        $this->configureSite('contact@diare.example');
        $this->persistOffer($this->openOffer('Déjà ouverte', 'deja-ouverte'));

        $this->submit('/nous-rejoindre/candidature-spontanee', [
            'job_application[desiredRole]' => 'Maintenance',
        ]);

        self::assertResponseRedirects('/nous-rejoindre/candidature-spontanee/confirmation');
        $application = $this->latest();
        self::assertNull($application->getJobOffer());
        self::assertTrue($application->isSpontaneous());
        self::assertSame('Maintenance', $application->getDesiredRole());
        self::assertSame('Maintenance', $application->getJobTitleSnapshot());
        self::assertSame(JobApplicationStatus::New, $application->getStatus());
        self::assertEmailCount(2);
        self::assertEmailSubjectContains($this->emailTo('contact@diare.example'), 'Maintenance');
        $candidate = $this->emailTo('amina@example.com');
        self::assertEmailHtmlBodyContains($candidate, 'candidature spontanée concernant « Maintenance »');
        self::assertEmailTextBodyContains($candidate, 'candidature spontanée concernant « Maintenance »');
        self::assertStringNotContainsString('au poste de', (string) $candidate->getHtmlBody());
        self::assertStringNotContainsString('au poste de', (string) $candidate->getTextBody());
    }

    public function testMissingAndInvalidFieldsAreRejected(): void
    {
        $this->persistOffer($this->openOffer('Qualité', 'qualite'));
        $url = '/nous-rejoindre/qualite/postuler';

        $this->submit($url, ['job_application[firstName]' => ''], withCv: false);
        self::assertResponseStatusCodeSame(422);
        $this->submit($url, ['job_application[lastName]' => '']);
        self::assertResponseStatusCodeSame(422);
        $this->submit($url, ['job_application[email]' => '']);
        self::assertResponseStatusCodeSame(422);
        $this->submit($url, ['job_application[email]' => 'pas-un-email']);
        self::assertResponseStatusCodeSame(422);
        $this->submit($url, ['job_application[phone]' => '']);
        self::assertResponseStatusCodeSame(422);
        $this->submit($url, ['job_application[phone]' => 'abc']);
        self::assertResponseStatusCodeSame(422);
        $this->submit($url, ['job_application[phone]' => '12']);
        self::assertResponseStatusCodeSame(422);
        $this->submit($url, ['job_application[motivation]' => '']);
        self::assertResponseStatusCodeSame(422);
        $this->submit($url, ['job_application[motivation]' => str_repeat('a', 2001)]);
        self::assertResponseStatusCodeSame(422);
        $this->submit($url, ['job_application[linkedinUrl]' => 'https://example.com/in/amina']);
        self::assertResponseStatusCodeSame(422);
        $this->submit($url, consent: false);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Le consentement est obligatoire.', (string) $this->client->getResponse()->getContent());
        $this->submit($url, withCv: false);
        self::assertResponseStatusCodeSame(422);

        self::assertSame(0, $this->countApplications());
        self::assertEmailCount(0);
    }

    public function testInternationalPhoneNumbersAreAccepted(): void
    {
        $this->configureSite('contact@diare.example');
        $this->persistOffer($this->openOffer('Atelier', 'atelier'));

        $this->submit('/nous-rejoindre/atelier/postuler', [
            'job_application[phone]' => '00224612345678',
            'job_application[linkedinUrl]' => 'https://www.linkedin.com/in/amina-diallo',
        ]);

        self::assertResponseRedirects();
        self::assertSame('00224612345678', $this->latest()->getPhone());
        self::assertSame('https://www.linkedin.com/in/amina-diallo', $this->latest()->getLinkedinUrl());
    }

    public function testFakePdfIsRejectedAndNothingIsStored(): void
    {
        $this->persistOffer($this->openOffer('Logistique', 'logistique'));
        $fake = $this->write('Ceci est un texte, pas un PDF.');

        $this->submit('/nous-rejoindre/logistique/postuler', pdf: $fake);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('PDF', (string) $this->client->getResponse()->getContent());
        self::assertSame(0, $this->countApplications());
        unlink($fake);
    }

    public function testOversizedPdfIsRejected(): void
    {
        $this->persistOffer($this->openOffer('Production', 'production'));
        $oversized = $this->pdf(5242881);

        $this->submit('/nous-rejoindre/production/postuler', pdf: $oversized);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('5 Mo', (string) $this->client->getResponse()->getContent());
        self::assertSame(0, $this->countApplications());
        if (is_file($oversized)) {
            unlink($oversized);
        }
    }

    public function testInvalidCsrfAndHoneypotDoNotPersist(): void
    {
        $this->configureSite('contact@diare.example');
        $this->persistOffer($this->openOffer('Sécurité', 'securite'));
        $url = '/nous-rejoindre/securite/postuler';

        $crawler = $this->client->request('GET', $url);
        $form = $crawler->selectButton('Envoyer ma candidature')->form();
        $this->fill($form, []);
        $form['job_application[_token]'] = 'jeton-invalide';
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->countApplications());

        $crawler = $this->client->request('GET', $url);
        $form = $crawler->selectButton('Envoyer ma candidature')->form();
        $this->fill($form, []);
        $form['job_application[website]'] = 'https://spam.example';
        $this->client->submit($form);
        self::assertResponseRedirects('/nous-rejoindre/securite/postuler/confirmation');
        self::assertSame(0, $this->countApplications());
        self::assertEmailCount(0);
    }

    public function testRepeatedSubmissionDoesNotDuplicateTheApplicationOrTheEmails(): void
    {
        $this->configureSite('contact@diare.example');
        $this->persistOffer($this->openOffer('Maintenance', 'maintenance'));
        $url = '/nous-rejoindre/maintenance/postuler';

        $this->submit($url);
        self::assertEmailCount(2);
        $this->submit($url);

        self::assertResponseRedirects('/nous-rejoindre/maintenance/postuler/confirmation');
        self::assertSame(1, $this->countApplications());
        self::assertEmailCount(0);
    }

    public function testMailFailureKeepsTheApplication(): void
    {
        $this->configureSite('contact@diare.example');
        $this->persistOffer($this->openOffer('Qualité', 'qualite-mail'));
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('transport indisponible'));
        static::getContainer()->set(MailerInterface::class, $mailer);

        $this->submit('/nous-rejoindre/qualite-mail/postuler');

        self::assertResponseRedirects();
        self::assertSame(1, $this->countApplications());
    }

    public function testCandidateStillReceivesConfirmationWhenNoHrAddressExists(): void
    {
        $this->persistOffer($this->openOffer('Sans destinataire', 'sans-destinataire'));

        $this->submit('/nous-rejoindre/sans-destinataire/postuler');

        self::assertResponseRedirects();
        self::assertSame(1, $this->countApplications());
        self::assertEmailCount(1);
        $candidate = $this->emailTo('amina@example.com');
        self::assertEmailAddressContains($candidate, 'To', 'amina@example.com');
        self::assertSame([], $candidate->getReplyTo());
    }

    public function testRateLimitBlocksTheNinthSubmission(): void
    {
        $this->configureSite('contact@diare.example');
        $this->persistOffer($this->openOffer('Atelier partagé', 'atelier-partage'));

        for ($attempt = 0; $attempt < 8; ++$attempt) {
            $this->submit('/nous-rejoindre/atelier-partage/postuler', [
                'job_application[email]' => 'candidat'.$attempt.'@example.com',
            ]);
            self::assertResponseRedirects();
        }

        $this->submit('/nous-rejoindre/atelier-partage/postuler', [
            'job_application[email]' => 'bloque@example.com',
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Trop de candidatures', (string) $this->client->getResponse()->getContent());
        self::assertSame(8, $this->countApplications());
    }

    public function testMissingCvDoesNotExhaustTheRateLimit(): void
    {
        $this->persistOffer($this->openOffer('Atelier CV', 'atelier-cv'));
        $url = '/nous-rejoindre/atelier-cv/postuler';

        for ($attempt = 0; $attempt < 9; ++$attempt) {
            $this->submit($url, [
                'job_application[email]' => 'sans-cv-'.$attempt.'@example.com',
            ], withCv: false);
            self::assertResponseStatusCodeSame(422);
            $content = (string) $this->client->getResponse()->getContent();
            self::assertStringContainsString('Le CV est obligatoire.', $content);
            self::assertStringNotContainsString('Trop de candidatures', $content);
        }

        $this->submit($url, [
            'job_application[email]' => 'avec-cv@example.com',
        ]);

        self::assertResponseRedirects();
        self::assertSame(1, $this->countApplications());
    }

    public function testRejectedRecaptchaDoesNotPersist(): void
    {
        self::ensureKernelShutdown();
        putenv('RECAPTCHA_ENABLED=1');
        $_SERVER['RECAPTCHA_ENABLED'] = $_ENV['RECAPTCHA_ENABLED'] = '1';
        $_SERVER['RECAPTCHA_SITE_KEY'] = $_ENV['RECAPTCHA_SITE_KEY'] = '';
        $_SERVER['RECAPTCHA_SECRET_KEY'] = $_ENV['RECAPTCHA_SECRET_KEY'] = '';
        $this->client = static::createClient();
        $this->persistOffer($this->openOffer('Contrôle', 'controle'));

        $this->submit('/nous-rejoindre/controle/postuler');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('La vérification anti-spam a échoué.', (string) $this->client->getResponse()->getContent());
        self::assertSame(0, $this->countApplications());
    }

    public function testCvIsPrivateAndAdminOnly(): void
    {
        $this->configureSite('contact@diare.example');
        $this->persistOffer($this->openOffer('Ressources humaines', 'ressources-humaines'));
        $this->submit('/nous-rejoindre/ressources-humaines/postuler');
        $application = $this->latest();
        $view = '/administration/candidatures/'.$application->getId().'/cv';
        $download = $view.'/telecharger';

        $this->client->request('GET', $view);
        self::assertResponseRedirects('/administration/connexion');
        $this->client->request('GET', '/uploads/job-applications/'.$application->getCvStoredFilename());
        self::assertResponseStatusCodeSame(404);

        $weak = $this->user('invite@diare.local', []);
        $this->client->loginUser($weak, 'admin');
        $this->client->request('GET', $view);
        self::assertResponseStatusCodeSame(403);

        $admin = $this->user('admin@diare.local', ['ROLE_ADMIN']);
        $this->client->loginUser($admin, 'admin');
        $this->client->request('GET', $view);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringContainsString('inline', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        $response = $this->client->getResponse();
        self::assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $response);
        self::assertStringStartsWith('%PDF-', (string) file_get_contents($response->getFile()->getPathname()));

        $this->client->request('GET', $download);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('attachment', (string) $this->client->getResponse()->headers->get('Content-Disposition'));

        $links = static::getContainer()->get(AdminLinkFactory::class);
        self::assertInstanceOf(AdminLinkFactory::class, $links);
        $index = $links->to(JobApplicationCrudController::class);
        $parts = parse_url($index);
        self::assertIsArray($parts);
        $this->client->request('GET', ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : ''));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Amina', (string) $this->client->getResponse()->getContent());

        $detail = $links->to(JobApplicationCrudController::class, Action::DETAIL, $application->getId());
        $detailParts = parse_url($detail);
        self::assertIsArray($detailParts);
        $this->client->request('GET', ($detailParts['path'] ?? '/').(isset($detailParts['query']) ? '?'.$detailParts['query'] : ''));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Consulter le CV', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('Télécharger le CV', (string) $this->client->getResponse()->getContent());
    }

    public function testDeletingTheOfferKeepsTheApplicationAndItsTitle(): void
    {
        $offer = $this->persistOffer($this->openOffer('Opérateur', 'operateur'));
        $this->submit('/nous-rejoindre/operateur/postuler');
        $id = $this->latest()->getId();
        $offerId = $offer->getId();

        $mapping = $this->manager()->getClassMetadata(JobApplication::class)->getAssociationMapping('jobOffer');
        self::assertSame('SET NULL', $mapping['joinColumns'][0]['onDelete'] ?? null);

        $managedOffer = $this->manager()->find(JobOffer::class, $offerId);
        self::assertInstanceOf(JobOffer::class, $managedOffer);
        $this->manager()->getConnection()->executeStatement('PRAGMA foreign_keys = ON');
        $this->manager()->remove($managedOffer);
        $this->manager()->flush();
        $this->manager()->clear();

        $offerIdLeft = $this->manager()->getConnection()->fetchOne('SELECT job_offer_id FROM job_application WHERE id = ?', [$id]);
        self::assertNull($offerIdLeft);

        $application = $this->manager()->find(JobApplication::class, $id);
        self::assertInstanceOf(JobApplication::class, $application);
        self::assertNull($application->getJobOffer());
        self::assertSame('Opérateur', $application->getJobTitleSnapshot());
        self::assertFalse($application->isSpontaneous());
    }

    public function testSpontaneousFormRequiresTheDesiredRole(): void
    {
        $this->submit('/nous-rejoindre/candidature-spontanee', [
            'job_application[desiredRole]' => '',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->countApplications());
    }

    private function configureSite(string $email): void
    {
        $settings = (new SiteSettings())->setEmail($email)->setCompanyName('Diaré Groupe Industrie');
        $this->manager()->persist($settings);
        $this->manager()->flush();
    }

    private function openOffer(string $title, string $slug): JobOffer
    {
        return (new JobOffer())
            ->setTitle($title)
            ->setSlug($slug)
            ->setLocation('Dubréka')
            ->setContractType(ContractType::Cdi)
            ->setIsPublished(true)
            ->setPublishedAt(new \DateTimeImmutable('-1 day'));
    }

    private function persistOffer(JobOffer $offer): JobOffer
    {
        $this->manager()->persist($offer);
        $this->manager()->flush();

        return $offer;
    }

    /** @param array<string, string> $override */
    private function submit(string $url, array $override = [], ?string $pdf = null, bool $withCv = true, bool $consent = true): void
    {
        $crawler = $this->client->request('GET', $url);
        $form = $crawler->selectButton('Envoyer ma candidature')->form();
        $this->fill($form, $override, $pdf, $withCv, $consent);
        $this->client->submit($form);
    }

    /**
     * @param array<string, string> $override
     */
    private function fill(\Symfony\Component\DomCrawler\Form $form, array $override, ?string $pdf = null, bool $withCv = true, bool $consent = true): void
    {
        $values = $override + [
            'job_application[firstName]' => 'Amina',
            'job_application[lastName]' => 'Diallo',
            'job_application[email]' => 'amina@example.com',
            'job_application[phone]' => '+224 612 34 56 78',
            'job_application[motivation]' => 'Je souhaite rejoindre Diaré Groupe Industrie pour mon expérience en production.',
            'job_application[availability]' => 'Sous un mois',
        ];
        foreach ($values as $name => $value) {
            if ($form->has($name)) {
                $form[$name] = $value;
            }
        }
        if ($consent) {
            $form['job_application[consent]']->tick();
        } else {
            $form['job_application[consent]']->untick();
        }
        if ($withCv) {
            $form['job_application[cv]']->upload($pdf ?? $this->pdf(1200));
        }
    }

    private function pdf(int $size): string
    {
        $header = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

        return $this->write(str_pad($header, max($size, strlen($header)), ' '));
    }

    private function write(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cvflow');
        self::assertIsString($path);
        $target = $path.'.pdf';
        file_put_contents($target, $contents);
        unlink($path);

        return $target;
    }

    private function latest(): JobApplication
    {
        $application = $this->manager()->getRepository(JobApplication::class)->findOneBy([], ['id' => 'DESC']);
        self::assertInstanceOf(JobApplication::class, $application);

        return $application;
    }

    private function countApplications(): int
    {
        return $this->manager()->getRepository(JobApplication::class)->count([]);
    }

    private function emailTo(string $recipient): \Symfony\Component\Mime\Email
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

    /** @param list<string> $roles */
    private function user(string $email, array $roles): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setFullName('Équipe RH')
            ->setRoles($roles);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        $user->setPassword($hasher->hashPassword($user, 'MotDePasse-Solide-12'));
        $this->manager()->persist($user);
        $this->manager()->flush();

        return $user;
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
