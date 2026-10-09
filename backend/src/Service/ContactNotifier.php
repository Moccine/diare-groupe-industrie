<?php

namespace App\Service;

use App\Controller\Admin\Crud\ContactRequestCrudController;
use App\Entity\ContactRequest;
use App\Entity\SiteSettings;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class ContactNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly AdminLinkFactory $adminLinks,
        private readonly ContactRecipientResolver $recipients,
        private readonly string $mailFromEmail,
        private readonly string $mailFromName,
    ) {
    }

    public function notify(ContactRequest $request, SiteSettings $settings): void
    {
        $staffAddress = $this->recipients->staff($settings);
        if ($staffAddress === null) {
            $this->logger->warning('Alerte administrateur de contact non envoyée : aucun destinataire configuré.', [
                'contactRequestId' => $request->getId(),
            ]);
        } else {
            try {
                $this->mailer->send($this->createStaffEmail($request, $settings, $staffAddress));
            } catch (\Throwable $exception) {
                $this->logger->error('Envoi de l’alerte administrateur impossible.', [
                    'contactRequestId' => $request->getId(),
                    'error' => $exception::class,
                ]);
            }
        }

        try {
            $this->mailer->send($this->createVisitorEmail($request, $settings));
        } catch (\Throwable $exception) {
            $this->logger->error('Envoi de l’accusé de réception impossible.', [
                'contactRequestId' => $request->getId(),
                'error' => $exception::class,
            ]);
        }
    }

    private function createStaffEmail(ContactRequest $request, SiteSettings $settings, string $staffAddress): TemplatedEmail
    {
        $email = $this->createEmail()
            ->to(new Address($staffAddress, $settings->getCompanyName()))
            ->subject('Nouveau message reçu — '.$this->subjectPart($request->getSubject()))
            ->htmlTemplate('email/contact_notification.html.twig')
            ->textTemplate('email/contact_notification.txt.twig')
            ->context($this->context($request, $settings, $this->adminUrl($request)));

        if (filter_var($request->getEmail(), FILTER_VALIDATE_EMAIL)) {
            $email->replyTo(new Address($request->getEmail(), $request->getFullName()));
        }

        return $email;
    }

    private function createVisitorEmail(ContactRequest $request, SiteSettings $settings): TemplatedEmail
    {
        $email = $this->createEmail()
            ->to(new Address($request->getEmail(), $request->getFullName()))
            ->subject('Nous avons bien reçu votre message — '.$settings->getCompanyName())
            ->htmlTemplate('email/contact_confirmation.html.twig')
            ->textTemplate('email/contact_confirmation.txt.twig')
            ->context($this->context($request, $settings));

        $replyTo = $this->recipients->replyTo($settings);
        if ($replyTo !== null) {
            $email->replyTo(new Address($replyTo, $settings->getCompanyName()));
        }

        return $email;
    }

    private function createEmail(): TemplatedEmail
    {
        return (new TemplatedEmail())
            ->from(new Address($this->mailFromEmail, $this->mailFromName));
    }

    /** @return array{request: ContactRequest, settings: SiteSettings, adminUrl: string} */
    private function context(ContactRequest $request, SiteSettings $settings, string $adminUrl = ''): array
    {
        return [
            'request' => $request,
            'settings' => $settings,
            'adminUrl' => $adminUrl,
        ];
    }

    private function adminUrl(ContactRequest $request): string
    {
        $id = $request->getId();
        if ($id === null) {
            return '';
        }

        try {
            $url = $this->adminLinks->to(ContactRequestCrudController::class, Action::DETAIL, $id);
        } catch (\Throwable $exception) {
            $this->logger->error('Lien d’administration du message indisponible.', [
                'contactRequestId' => $id,
                'error' => $exception::class,
            ]);

            return '';
        }

        return $this->adminLinks->qualify($url);
    }

    private function subjectPart(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $value)) ?? '');

        return $value !== '' ? $value : 'sans objet';
    }
}
