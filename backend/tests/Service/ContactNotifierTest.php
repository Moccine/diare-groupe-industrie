<?php

namespace App\Tests\Service;

use App\Entity\ContactRequest;
use App\Entity\SiteSettings;
use App\Service\AdminLinkFactory;
use App\Service\ContactNotifier;
use App\Service\ContactRecipientResolver;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

final class ContactNotifierTest extends TestCase
{
    public function testStaffNotificationRepliesToTheVisitorAndLinksToTheAdminRecord(): void
    {
        $request = $this->request();
        $this->assignId($request, 9);
        $sent = [];
        $notifier = $this->notifier($sent, 'https://diare.example/administration?crudAction=detail&entityId=9');

        $notifier->notify($request, $this->settings());

        self::assertCount(2, $sent);
        $staff = $sent[0];
        $visitor = $sent[1];
        self::assertSame('Nouveau message reçu — Demande commerciale', $staff->getSubject());
        self::assertSame('contact@diare.example', $staff->getTo()[0]->getAddress());
        self::assertSame('Diaré Groupe Industrie', $staff->getFrom()[0]->getName());
        self::assertSame('amina@example.com', $staff->getReplyTo()[0]->getAddress());
        self::assertSame('email/contact_notification.html.twig', $staff->getHtmlTemplate());
        self::assertSame('email/contact_notification.txt.twig', $staff->getTextTemplate());
        self::assertSame('https://diare.example/administration?crudAction=detail&entityId=9', $staff->getContext()['adminUrl']);
        self::assertCount(0, $staff->getAttachments());

        self::assertSame('amina@example.com', $visitor->getTo()[0]->getAddress());
        self::assertSame('contact@diare.example', $visitor->getReplyTo()[0]->getAddress());
        self::assertSame('email/contact_confirmation.html.twig', $visitor->getHtmlTemplate());
        self::assertSame('', $visitor->getContext()['adminUrl']);
        self::assertCount(0, $visitor->getAttachments());
    }

    public function testSubjectNewlinesAreCollapsed(): void
    {
        $request = $this->request()->setSubject("Objet\nBcc: evil@example.com");
        $sent = [];
        $this->notifier($sent)->notify($request, $this->settings());

        self::assertSame('Nouveau message reçu — Objet Bcc: evil@example.com', $sent[0]->getSubject());
        self::assertStringNotContainsString("\n", (string) $sent[0]->getSubject());
    }

    public function testNotifyEmailTakesPriorityOverThePublicAddress(): void
    {
        $sent = [];
        $this->notifier($sent, notifyEmail: 'alertes@example.test')->notify($this->request(), $this->settings());

        self::assertCount(2, $sent);
        self::assertSame('alertes@example.test', $sent[0]->getTo()[0]->getAddress());
        self::assertSame('contact@diare.example', $sent[1]->getReplyTo()[0]->getAddress());
        self::assertSame('amina@example.com', $sent[1]->getTo()[0]->getAddress());
    }

    public function testNotifyEmailAloneStillConfirmsTheVisitorWithoutReplyTo(): void
    {
        $sent = [];
        $this->notifier($sent, notifyEmail: 'alertes@example.test')->notify($this->request(), new SiteSettings());

        self::assertCount(2, $sent);
        self::assertSame('alertes@example.test', $sent[0]->getTo()[0]->getAddress());
        self::assertSame('amina@example.com', $sent[1]->getTo()[0]->getAddress());
        self::assertSame([], $sent[1]->getReplyTo());
    }

    public function testInvalidNotifyEmailFallsBackToThePublicAddress(): void
    {
        $sent = [];
        $this->notifier($sent, notifyEmail: 'pas-une-adresse')->notify($this->request(), $this->settings());

        self::assertSame('contact@diare.example', $sent[0]->getTo()[0]->getAddress());
        self::assertSame('contact@diare.example', $sent[1]->getReplyTo()[0]->getAddress());
    }

    public function testMissingAdminRecipientStillConfirmsTheVisitor(): void
    {
        $sent = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->willReturnCallback(function (string $message, array $context): void {
            self::assertStringContainsString('destinataire', $message);
            $encoded = json_encode($context);
            self::assertIsString($encoded);
            self::assertStringNotContainsString('amina@example.com', $encoded);
            self::assertStringNotContainsString('script', $encoded);
            self::assertArrayNotHasKey('message', $context);
        });

        $this->notifier($sent, logger: $logger)->notify($this->request(), new SiteSettings());

        self::assertCount(1, $sent);
        self::assertSame('amina@example.com', $sent[0]->getTo()[0]->getAddress());
        self::assertSame([], $sent[0]->getReplyTo());
        self::assertSame('email/contact_confirmation.html.twig', $sent[0]->getHtmlTemplate());
    }

    public function testAdminFailureStillAttemptsTheVisitorReceipt(): void
    {
        $sent = [];
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(2))->method('send')->willReturnCallback(function (RawMessage $message) use (&$sent): void {
            self::assertInstanceOf(TemplatedEmail::class, $message);
            $sent[] = $message;
            if (count($sent) === 1) {
                throw new TransportException('SMTP indisponible');
            }
        });
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->willReturnCallback(function (string $message, array $context): void {
            self::assertStringContainsString('alerte administrateur', $message);
            $this->assertLogHidesTheMessage($context);
        });

        $this->notifier(mailer: $mailer, logger: $logger)->notify($this->request(), $this->settings());

        self::assertSame('contact@diare.example', $sent[0]->getTo()[0]->getAddress());
        self::assertSame('amina@example.com', $sent[1]->getTo()[0]->getAddress());
    }

    public function testVisitorFailureStillSendsTheAdminAlert(): void
    {
        $sent = [];
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(2))->method('send')->willReturnCallback(function (RawMessage $message) use (&$sent): void {
            self::assertInstanceOf(TemplatedEmail::class, $message);
            $sent[] = $message;
            if (count($sent) === 2) {
                throw new TransportException('SMTP indisponible');
            }
        });
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->willReturnCallback(function (string $message, array $context): void {
            self::assertStringContainsString('accusé de réception', $message);
            $this->assertLogHidesTheMessage($context);
        });

        $this->notifier(mailer: $mailer, logger: $logger)->notify($this->request(), $this->settings());

        self::assertSame('contact@diare.example', $sent[0]->getTo()[0]->getAddress());
        self::assertSame('amina@example.com', $sent[1]->getTo()[0]->getAddress());
    }

    public function testTransportFailuresDoNotEscape(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(2))->method('send')->willThrowException(new TransportException('SMTP indisponible'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('error');
        $notifier = $this->notifier(mailer: $mailer, logger: $logger);

        $notifier->notify($this->request(), $this->settings());
    }

    /** @param array<string, mixed> $context */
    private function assertLogHidesTheMessage(array $context): void
    {
        $encoded = json_encode($context);
        self::assertIsString($encoded);
        self::assertStringNotContainsString('amina@example.com', $encoded);
        self::assertStringNotContainsString('script', $encoded);
        self::assertSame(['contactRequestId', 'error'], array_keys($context));
    }

    private function request(): ContactRequest
    {
        return (new ContactRequest())
            ->setFirstName('Amina')
            ->setLastName('Diallo')
            ->setCompany('Diaré Distribution')
            ->setEmail('amina@example.com')
            ->setPhone('620000000')
            ->setSubject('Demande commerciale')
            ->setMessage("Bonjour\n<script>alert(1)</script>")
            ->setConsent(true);
    }

    private function settings(): SiteSettings
    {
        return (new SiteSettings())
            ->setCompanyName('Diaré Groupe Industrie')
            ->setEmail('contact@diare.example');
    }

    private function assignId(ContactRequest $request, int $id): void
    {
        $property = new \ReflectionProperty(ContactRequest::class, 'id');
        $property->setValue($request, $id);
    }

    /** @param list<TemplatedEmail> $sent */
    private function notifier(
        array &$sent = [],
        string $adminUrl = '',
        ?MailerInterface $mailer = null,
        ?LoggerInterface $logger = null,
        string $notifyEmail = '',
    ): ContactNotifier {
        if ($mailer === null) {
            $mailer = $this->createMock(MailerInterface::class);
            $mailer->method('send')->willReturnCallback(function (RawMessage $message) use (&$sent): void {
                self::assertInstanceOf(TemplatedEmail::class, $message);
                $sent[] = $message;
            });
        }

        return new ContactNotifier(
            $mailer,
            $logger ?? $this->createMock(LoggerInterface::class),
            $this->links($adminUrl),
            new ContactRecipientResolver($notifyEmail),
            'noreply@diare.example',
            'Diaré Groupe Industrie',
        );
    }

    private function links(string $adminUrl): AdminLinkFactory
    {
        $generator = $this->createMock(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setDashboard')->willReturnSelf();
        $generator->method('setController')->willReturnSelf();
        $generator->method('setAction')->willReturnSelf();
        $generator->method('setEntityId')->willReturnSelf();
        $generator->method('generateUrl')->willReturn($adminUrl);

        return new AdminLinkFactory($generator, 'https://diare.example');
    }
}
