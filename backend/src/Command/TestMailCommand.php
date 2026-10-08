<?php

namespace App\Command;

use App\Service\PublicContent;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Throwable;

#[AsCommand(
    name: 'app:mail:test',
    description: 'Envoie un e-mail de test pour vérifier la configuration du mailer.',
)]
final class TestMailCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly PublicContent $publicContent,
        private readonly LoggerInterface $logger,
        private readonly string $mailFromEmail,
        private readonly string $mailFromName,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::REQUIRED, 'Adresse du destinataire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = trim((string) $input->getArgument('to'));

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $io->error('L’adresse destinataire est invalide.');

            return Command::FAILURE;
        }

        $settings = $this->publicContent->settings();
        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFromEmail, $this->mailFromName))
            ->to($to)
            ->subject('Email de test — '.$settings->getCompanyName())
            ->htmlTemplate('email/mail_test.html.twig')
            ->textTemplate('email/mail_test.txt.twig')
            ->context([
                'settings' => $settings,
                'recipient' => $to,
            ]);

        try {
            $this->mailer->send($email);
        } catch (Throwable $exception) {
            $this->logger->error('Envoi de l’email de test impossible.', [
                'error' => $exception::class,
            ]);
            $io->error('Envoi impossible.');

            return Command::FAILURE;
        }

        $io->success(sprintf('Email de test envoyé à %s.', $to));

        return Command::SUCCESS;
    }
}
