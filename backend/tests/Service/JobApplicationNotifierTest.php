<?php

namespace App\Tests\Service;

use App\Entity\JobApplication;
use App\Entity\SiteSettings;
use App\Service\AdminLinkFactory;
use App\Service\JobApplicationNotifier;
use App\Service\JobApplicationRecipientResolver;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

final class JobApplicationNotifierTest extends TestCase
{
    public function testStaffAlertRepliesToTheCandidateAndDoesNotAttachTheCv(): void
    {
        $application = $this->application();
        $this->assignId($application, 12);
        $sent = [];
        $notifier = $this->notifier(
            'rh-poste@example.com',
            $sent,
            'https://diare.example/administration?crudAction=detail&entityId=12',
        );

        $notifier->notify($application, (new SiteSettings())->setCompanyName('Diaré Groupe Industrie'));

        self::assertCount(2, $sent);
        $staff = $sent[0];
        $candidate = $sent[1];
        self::assertInstanceOf(TemplatedEmail::class, $staff);
        self::assertInstanceOf(TemplatedEmail::class, $candidate);

        self::assertSame('[DGI] Nouvelle candidature — Conducteur de ligne', $staff->getSubject());
        self::assertSame('rh-poste@example.com', $staff->getTo()[0]->getAddress());
        self::assertSame('noreply@diare.example', $staff->getFrom()[0]->getAddress());
        self::assertSame('Diaré Groupe Industrie', $staff->getFrom()[0]->getName());
        self::assertSame('amina@example.com', $staff->getReplyTo()[0]->getAddress());
        self::assertSame('email/job_application_notification.html.twig', $staff->getHtmlTemplate());
        self::assertSame('email/job_application_notification.txt.twig', $staff->getTextTemplate());
        self::assertSame('https://diare.example/administration?crudAction=detail&entityId=12', $staff->getContext()['adminUrl']);
        self::assertSame(['application', 'settings', 'adminUrl'], array_keys($staff->getContext()));
        self::assertCount(0, $staff->getAttachments());

        self::assertSame('Confirmation de votre candidature — Diaré Groupe Industrie', $candidate->getSubject());
        self::assertSame('amina@example.com', $candidate->getTo()[0]->getAddress());
        self::assertSame('rh-poste@example.com', $candidate->getReplyTo()[0]->getAddress());
        self::assertSame('', $candidate->getContext()['adminUrl']);
        self::assertCount(0, $candidate->getAttachments());
    }

    public function testSubjectNewlinesCannotBecomeExtraHeaders(): void
    {
        $application = $this->application()->setJobTitleSnapshot("Conducteur\nBcc: evil@example.com");
        $sent = [];
        $this->notifier('rh@example.com', $sent)->notify($application, new SiteSettings());

        self::assertSame('[DGI] Nouvelle candidature — Conducteur Bcc: evil@example.com', $sent[0]->getSubject());
        self::assertStringNotContainsString("\n", (string) $sent[0]->getSubject());
    }

    public function testMissingRecipientStillConfirmsToTheCandidateWithoutAReplyTo(): void
    {
        $sent = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $logger->expects(self::never())->method('error');
        $notifier = $this->notifier(null, $sent, logger: $logger);

        $notifier->notify($this->application(), new SiteSettings());

        self::assertCount(1, $sent);
        self::assertSame('amina@example.com', $sent[0]->getTo()[0]->getAddress());
        self::assertSame([], $sent[0]->getReplyTo());
    }

    public function testTransportFailuresDoNotEscape(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(2))->method('send')->willThrowException(new TransportException('transport indisponible'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('error')->willReturnCallback(function (string $message, array $context): void {
            $encoded = json_encode($context);
            self::assertIsString($encoded);
            self::assertStringNotContainsString('amina@example.com', $encoded);
            self::assertStringNotContainsString('note interne', $encoded);
        });
        $notifier = $this->notifier('rh@example.com', logger: $logger, mailer: $mailer);

        $notifier->notify($this->application()->setInternalNotes('note interne secrète'), new SiteSettings());
    }

    public function testABrokenAdminLinkDoesNotBlockTheEmails(): void
    {
        $application = $this->application();
        $this->assignId($application, 4);
        $sent = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $notifier = $this->notifier('rh@example.com', $sent, logger: $logger, links: $this->links(new \RuntimeException('easyadmin')));

        $notifier->notify($application, new SiteSettings());

        self::assertCount(2, $sent);
        self::assertSame('', $sent[0]->getContext()['adminUrl']);
    }

    private function application(): JobApplication
    {
        $application = (new JobApplication())
            ->setFirstName('Amina')
            ->setLastName('Diallo')
            ->setEmail('amina@example.com')
            ->setPhone('620000000')
            ->setMotivation('Je souhaite rejoindre l’équipe.')
            ->setJobTitleSnapshot('Conducteur de ligne')
            ->setInternalNotes('note interne secrète');
        $application->attachCv('secret.pdf', 'cv-amina.pdf', 'application/pdf', 1200);

        return $application;
    }

    private function assignId(JobApplication $application, int $id): void
    {
        $property = new \ReflectionProperty(JobApplication::class, 'id');
        $property->setValue($application, $id);
    }

    /** @param list<TemplatedEmail> $sent */
    private function notifier(
        ?string $recipient,
        array &$sent = [],
        string $adminUrl = '',
        ?LoggerInterface $logger = null,
        ?MailerInterface $mailer = null,
        ?AdminLinkFactory $links = null,
    ): JobApplicationNotifier {
        if ($mailer === null) {
            $mailer = $this->createMock(MailerInterface::class);
            $mailer->method('send')->willReturnCallback(function (RawMessage $message) use (&$sent): void {
                self::assertInstanceOf(TemplatedEmail::class, $message);
                $sent[] = $message;
            });
        }

        return new JobApplicationNotifier(
            $mailer,
            $logger ?? $this->createMock(LoggerInterface::class),
            new JobApplicationRecipientResolver($recipient ?? ''),
            $links ?? $this->links($adminUrl),
            'noreply@diare.example',
            'Diaré Groupe Industrie',
        );
    }

    private function links(string|\Throwable $result): AdminLinkFactory
    {
        $generator = $this->createMock(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setDashboard')->willReturnSelf();
        $generator->method('setController')->willReturnSelf();
        $generator->method('setAction')->willReturnSelf();
        $generator->method('setEntityId')->willReturnSelf();
        if ($result instanceof \Throwable) {
            $generator->method('generateUrl')->willThrowException($result);
        } else {
            $generator->method('generateUrl')->willReturn($result);
        }

        return new AdminLinkFactory($generator, 'https://diare.example');
    }
}
