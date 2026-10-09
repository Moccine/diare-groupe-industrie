<?php

namespace App\Tests\Mailer;

use App\Entity\ContactRequest;
use App\Entity\JobApplication;
use App\Entity\SiteSettings;
use App\Entity\User;
use App\Service\EmailBranding;
use App\Twig\EmailExtension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class TransactionalEmailRenderingTest extends TestCase
{
    public function testOfferConfirmationRendersHtmlAndTextWithoutALocalPath(): void
    {
        $application = $this->application(false);
        $html = $this->render('email/job_application_confirmation.html.twig', $this->jobContext($application));
        $text = $this->render('email/job_application_confirmation.txt.twig', $this->jobContext($application));

        foreach ([$html, $text] as $body) {
            self::assertStringContainsString('Bonjour Amina', $body);
            self::assertStringContainsString('au poste de « Conducteur de ligne »', $body);
            self::assertStringContainsString('Diaré Groupe Industrie', $body);
            self::assertStringContainsString('Message transactionnel', $body);
            $this->assertClean($body);
        }

        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('localhost', $html);
        self::assertStringContainsString('Zone de recette', $html);
        self::assertStringContainsString('contact@diare.example', $html);
    }

    public function testSpontaneousConfirmationDoesNotInventAJobTitle(): void
    {
        $withRole = $this->application(true, 'Maintenance');
        $html = $this->render('email/job_application_confirmation.html.twig', $this->jobContext($withRole));
        $text = $this->render('email/job_application_confirmation.txt.twig', $this->jobContext($withRole));

        self::assertStringContainsString('candidature spontanée concernant « Maintenance »', $html);
        self::assertStringContainsString('candidature spontanée concernant « Maintenance »', $text);
        self::assertStringNotContainsString('au poste de', $html);
        self::assertStringNotContainsString('au poste de', $text);

        $withoutRole = $this->application(true, null);
        $bare = $this->render('email/job_application_confirmation.html.twig', $this->jobContext($withoutRole));
        self::assertStringContainsString('bonne réception de votre candidature spontanée.', $bare);
        self::assertStringNotContainsString('au poste de', $bare);
        self::assertStringNotContainsString('« Candidature spontanée »', $bare);
    }

    public function testStaffApplicationEmailEscapesInputAndOmitsPrivateData(): void
    {
        $application = $this->application(false);
        $property = new \ReflectionProperty(JobApplication::class, 'motivation');
        $property->setValue($application, "Ligne une\n<script>alert(1)</script>");
        $createdAt = new \ReflectionProperty(JobApplication::class, 'createdAt');
        $createdAt->setValue($application, new \DateTimeImmutable('2026-10-09 08:15:00'));
        $application->attachCv('secret.pdf', 'cv-amina.pdf', 'application/pdf', 1200);
        $application->setInternalNotes('note interne secrète');

        $context = $this->jobContext($application, 'https://diare.example/administration?crudAction=detail&entityId=12');
        $html = $this->render('email/job_application_notification.html.twig', $context);
        $text = $this->render('email/job_application_notification.txt.twig', $context);

        self::assertStringContainsString('Amina Diallo', $html);
        self::assertStringContainsString('Conducteur de ligne', $html);
        self::assertStringContainsString('amina@example.com', $html);
        self::assertStringContainsString('09/10/2026 08:15', $html);
        self::assertStringContainsString('href="https://diare.example/administration?crudAction=detail&amp;entityId=12"', $html);
        self::assertStringContainsString('Consulter la candidature', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('<br', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringContainsString('Consulter la candidature : https://diare.example/administration?crudAction=detail&entityId=12', $text);
        self::assertStringContainsString("<script>alert(1)</script>", $text);

        foreach ([$html, $text] as $body) {
            self::assertStringNotContainsString('note interne', $body);
            self::assertStringNotContainsString('secret.pdf', $body);
            self::assertStringNotContainsString('cv-amina.pdf', $body);
            self::assertStringNotContainsString('job-applications', $body);
            self::assertStringNotContainsString('localhost', $body);
            $this->assertClean($body);
        }
    }

    public function testSpontaneousStaffEmailNamesTheDesiredDomain(): void
    {
        $application = $this->application(true, 'Maintenance');
        $html = $this->render('email/job_application_notification.html.twig', $this->jobContext($application, 'https://diare.example/administration'));

        self::assertStringContainsString('Candidature spontanée — Maintenance', $html);
        self::assertStringNotContainsString('au poste de', $html);
    }

    public function testContactEmailsEscapeTheMessageAndKeepTheAdminLinkPrivate(): void
    {
        $request = (new ContactRequest())
            ->setFirstName('Amina')
            ->setLastName('Diallo')
            ->setCompany('Diaré Distribution')
            ->setEmail('amina@example.com')
            ->setPhone('620000000')
            ->setSubject('Demande commerciale')
            ->setMessage("Bonjour\n<script>alert(1)</script>")
            ->setConsent(true);
        $createdAt = new \ReflectionProperty(ContactRequest::class, 'createdAt');
        $createdAt->setValue($request, new \DateTimeImmutable('2026-10-09 08:15:00'));
        $settings = $this->settings();

        $staff = $this->render('email/contact_notification.html.twig', [
            'request' => $request,
            'settings' => $settings,
            'adminUrl' => 'https://diare.example/administration?crudAction=detail&entityId=3',
        ]);
        $staffText = $this->render('email/contact_notification.txt.twig', [
            'request' => $request,
            'settings' => $settings,
            'adminUrl' => 'https://diare.example/administration?crudAction=detail&entityId=3',
        ]);
        $visitor = $this->render('email/contact_confirmation.html.twig', [
            'request' => $request,
            'settings' => $settings,
            'adminUrl' => '',
        ]);
        $visitorText = $this->render('email/contact_confirmation.txt.twig', [
            'request' => $request,
            'settings' => $settings,
        ]);

        self::assertStringContainsString('Amina Diallo', $staff);
        self::assertStringContainsString('Diaré Distribution', $staff);
        self::assertStringContainsString('Demande commerciale', $staff);
        self::assertStringContainsString('09/10/2026 08:15', $staff);
        self::assertStringContainsString('Consulter le message', $staff);
        self::assertStringContainsString('&lt;script&gt;', $staff);
        self::assertStringNotContainsString('<script', $staff);
        self::assertStringContainsString('<br', $staff);
        self::assertStringContainsString('Consulter le message : https://diare.example/administration', $staffText);
        self::assertStringNotContainsString('/administration', $visitor);
        self::assertStringNotContainsString('/administration', $visitorText);
        self::assertStringContainsString('Bonjour Amina', $visitor);
        self::assertStringContainsString('concernant « Demande commerciale »', $visitorText);

        foreach ([$staff, $staffText, $visitor, $visitorText] as $body) {
            $this->assertClean($body);
        }
    }

    public function testPasswordResetAndMailTestUseTheSharedLayout(): void
    {
        $settings = $this->settings();
        $reset = $this->render('email/password_reset.html.twig', [
            'settings' => $settings,
            'user' => (new User())->setFullName('Équipe RH')->setEmail('rh@diare.example'),
            'resetUrl' => 'https://diare.example/administration/reinitialiser-mot-de-passe/jeton',
        ]);
        $resetText = $this->render('email/password_reset.txt.twig', [
            'settings' => $settings,
            'user' => (new User())->setFullName('Équipe RH')->setEmail('rh@diare.example'),
            'resetUrl' => 'https://diare.example/administration/reinitialiser-mot-de-passe/jeton',
        ]);
        $test = $this->render('email/mail_test.html.twig', [
            'settings' => $settings,
            'recipient' => 'qa@example.com',
        ]);
        $testText = $this->render('email/mail_test.txt.twig', [
            'settings' => $settings,
            'recipient' => 'qa@example.com',
        ]);

        self::assertStringContainsString('Choisir un nouveau mot de passe', $reset);
        self::assertStringContainsString('https://diare.example/administration/reinitialiser-mot-de-passe/jeton', $reset);
        self::assertStringContainsString('ignorez ce message', $resetText);
        self::assertStringContainsString('email de test', $test);
        self::assertStringContainsString('qa@example.com', $testText);
        self::assertStringContainsString('Diaré Groupe Industrie', $test);

        foreach ([$reset, $resetText, $test, $testText] as $body) {
            self::assertStringContainsString('Message transactionnel', $body);
            $this->assertClean($body);
        }
    }

    public function testAPublicHttpsLogoIsAbsoluteAndAHostileColorIsDiscarded(): void
    {
        $settings = $this->settings()
            ->setPrimaryColor('#112233')
            ->setSecondaryColor('#fff;background:url(https://evil.example)')
            ->setAccentColor('#90B43C');
        $html = $this->render(
            'email/mail_test.html.twig',
            ['settings' => $settings, 'recipient' => 'qa@example.com'],
            'https://diare.example',
        );

        self::assertStringContainsString('src="https://diare.example/brand/logo.png"', $html);
        self::assertStringContainsString('#112233', $html);
        self::assertStringContainsString('#0E3A18', $html);
        self::assertStringNotContainsString('evil.example', $html);
        self::assertStringNotContainsString('localhost', $html);
        $this->assertClean($html);
    }

    private function application(bool $spontaneous, ?string $role = null): JobApplication
    {
        $application = (new JobApplication())
            ->setFirstName('Amina')
            ->setLastName('Diallo')
            ->setEmail('amina@example.com')
            ->setPhone('620000000')
            ->setMotivation('Je souhaite rejoindre l’équipe.')
            ->setDesiredRole($role);

        if ($spontaneous) {
            $application->captureContext(null);
        } else {
            $application->setJobTitleSnapshot('Conducteur de ligne');
        }

        return $application;
    }

    /** @return array{application: JobApplication, settings: SiteSettings, adminUrl: string} */
    private function jobContext(JobApplication $application, string $adminUrl = ''): array
    {
        return [
            'application' => $application,
            'settings' => $this->settings(),
            'adminUrl' => $adminUrl,
        ];
    }

    private function settings(): SiteSettings
    {
        return (new SiteSettings())
            ->setCompanyName('Diaré Groupe Industrie')
            ->setEmail('contact@diare.example')
            ->setPhone('+224 600 00 00 00')
            ->setAddress('Zone de recette')
            ->setPrimaryColor('#185424')
            ->setSecondaryColor('#0E3A18')
            ->setAccentColor('#90B43C');
    }

    /** @param array<string, mixed> $context */
    private function render(string $template, array $context, string $siteUrl = 'http://localhost:8086'): string
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates'), [
            'strict_variables' => true,
            'autoescape' => 'name',
        ]);
        $twig->addExtension(new EmailExtension(new EmailBranding($siteUrl)));

        return $twig->render($template, $context);
    }

    private function assertClean(string $body): void
    {
        self::assertStringNotContainsString('{{', $body);
        self::assertStringNotContainsString('}}', $body);
        self::assertStringNotContainsString('/var/', $body);
        self::assertStringNotContainsString('file://', $body);
        self::assertDoesNotMatchRegularExpression('#(?:^|[\s"\'])/(?:Users|home|var)/#', $body);
    }
}
