<?php

namespace App\Service;

use App\Controller\Admin\Crud\JobApplicationCrudController;
use App\Entity\JobApplication;
use App\Entity\SiteSettings;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class JobApplicationNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly JobApplicationRecipientResolver $recipients,
        private readonly AdminLinkFactory $adminLinks,
        private readonly string $mailFromEmail,
        private readonly string $mailFromName,
    ) {
    }

    public function notify(JobApplication $application, SiteSettings $settings): void
    {
        $recipient = $this->recipients->resolve($application->getJobOffer(), $settings);
        $adminUrl = $this->adminUrl($application);

        if ($recipient === null) {
            $this->logger->warning('Notification RH non envoyée : aucune adresse configurée.', [
                'applicationId' => $application->getId(),
            ]);
        } else {
            try {
                $this->mailer->send($this->staffEmail($application, $settings, $recipient, $adminUrl));
            } catch (\Throwable $exception) {
                $this->logger->error('Notification RH de candidature impossible.', [
                    'applicationId' => $application->getId(),
                    'error' => $exception::class,
                ]);
            }
        }

        try {
            $this->mailer->send($this->candidateEmail($application, $settings, $recipient));
        } catch (\Throwable $exception) {
            $this->logger->error('Accusé de candidature impossible.', [
                'applicationId' => $application->getId(),
                'error' => $exception::class,
            ]);
        }
    }

    private function staffEmail(JobApplication $application, SiteSettings $settings, string $recipient, string $adminUrl): TemplatedEmail
    {
        $email = $this->baseEmail()
            ->to(new Address($recipient, $settings->getCompanyName()))
            ->subject('[DGI] Nouvelle candidature — '.$this->subjectPart($application->getJobTitleSnapshot(), 'Candidature'))
            ->htmlTemplate('email/job_application_notification.html.twig')
            ->textTemplate('email/job_application_notification.txt.twig')
            ->context($this->context($application, $settings, $adminUrl));

        if (filter_var($application->getEmail(), FILTER_VALIDATE_EMAIL)) {
            $email->replyTo(new Address($application->getEmail(), $application->getFullName()));
        }

        return $email;
    }

    private function candidateEmail(JobApplication $application, SiteSettings $settings, ?string $recipient): TemplatedEmail
    {
        $email = $this->baseEmail()
            ->to(new Address($application->getEmail(), $application->getFullName()))
            ->subject('Confirmation de votre candidature — Diaré Groupe Industrie')
            ->htmlTemplate('email/job_application_confirmation.html.twig')
            ->textTemplate('email/job_application_confirmation.txt.twig')
            ->context($this->context($application, $settings, ''));

        if ($recipient !== null) {
            $email->replyTo(new Address($recipient, $settings->getCompanyName()));
        }

        return $email;
    }

    private function baseEmail(): TemplatedEmail
    {
        return (new TemplatedEmail())
            ->from(new Address($this->mailFromEmail, $this->mailFromName));
    }

    /** @return array{application: JobApplication, settings: SiteSettings, adminUrl: string} */
    private function context(JobApplication $application, SiteSettings $settings, string $adminUrl): array
    {
        return [
            'application' => $application,
            'settings' => $settings,
            'adminUrl' => $adminUrl,
        ];
    }

    private function adminUrl(JobApplication $application): string
    {
        $id = $application->getId();
        if ($id === null) {
            return '';
        }

        try {
            $url = $this->adminLinks->to(JobApplicationCrudController::class, Action::DETAIL, $id);
        } catch (\Throwable $exception) {
            $this->logger->error('Lien d’administration de la candidature indisponible.', [
                'applicationId' => $id,
                'error' => $exception::class,
            ]);

            return '';
        }

        return $this->adminLinks->qualify($url);
    }

    private function subjectPart(string $value, string $fallback): string
    {
        $value = trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $value)) ?? '');

        return $value !== '' ? $value : $fallback;
    }
}
