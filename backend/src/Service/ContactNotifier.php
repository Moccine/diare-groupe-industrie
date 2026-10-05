<?php

namespace App\Service;

use App\Entity\ContactRequest;
use App\Entity\SiteSettings;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class ContactNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $mailFromEmail,
        private readonly string $mailFromName,
    ) {
    }

    public function notify(ContactRequest $request, SiteSettings $settings): void
    {
        if (!$settings->hasPublicEmail()) {
            return;
        }

        try {
            $this->mailer->send($this->createStaffEmail($request, $settings));
        } catch (\Throwable $exception) {
            $this->logger->error('Envoi de la notification de contact impossible.', [
                'error' => $exception::class,
            ]);
        }

        try {
            $this->mailer->send($this->createVisitorEmail($request, $settings));
        } catch (\Throwable $exception) {
            $this->logger->error('Envoi de l’accusé de réception impossible.', [
                'error' => $exception::class,
            ]);
        }
    }

    private function createStaffEmail(ContactRequest $request, SiteSettings $settings): TemplatedEmail
    {
        $email = $this->createEmail()
            ->to(new Address((string) $settings->getEmail(), $settings->getCompanyName()))
            ->subject('Nouveau message reçu — '.$request->getSubject())
            ->htmlTemplate('email/contact_notification.html.twig')
            ->textTemplate('email/contact_notification.txt.twig')
            ->context($this->context($request, $settings));

        if (filter_var($request->getEmail(), FILTER_VALIDATE_EMAIL)) {
            $email->replyTo(new Address($request->getEmail(), $request->getFullName()));
        }

        return $email;
    }

    private function createVisitorEmail(ContactRequest $request, SiteSettings $settings): TemplatedEmail
    {
        $email = $this->createEmail()
            ->to(new Address($request->getEmail(), $request->getFullName()))
            ->replyTo(new Address((string) $settings->getEmail(), $settings->getCompanyName()))
            ->subject('Nous avons bien reçu votre message — '.$settings->getCompanyName())
            ->htmlTemplate('email/contact_confirmation.html.twig')
            ->textTemplate('email/contact_confirmation.txt.twig')
            ->context($this->context($request, $settings));

        return $email;
    }

    private function createEmail(): TemplatedEmail
    {
        return (new TemplatedEmail())
            ->from(new Address($this->mailFromEmail, $this->mailFromName));
    }

    /** @return array{request: ContactRequest, settings: SiteSettings} */
    private function context(ContactRequest $request, SiteSettings $settings): array
    {
        return [
            'request' => $request,
            'settings' => $settings,
        ];
    }
}
