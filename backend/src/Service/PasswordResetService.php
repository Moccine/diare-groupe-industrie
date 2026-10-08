<?php

namespace App\Service;

use App\Entity\SiteSettings;
use App\Entity\User;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

final readonly class PasswordResetService
{
    private const TOKEN_TTL = '+1 hour';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private MailerInterface $mailer,
        private UserPasswordHasherInterface $passwordHasher,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
        private string $mailFromEmail,
        private string $mailFromName,
    ) {
    }

    public function generateResetToken(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        $user->setResetToken(hash('sha256', $token));
        $user->setResetTokenExpiresAt(new DateTimeImmutable(self::TOKEN_TTL));
        $this->entityManager->flush();

        return $token;
    }

    public function sendResetEmail(User $user, #[\SensitiveParameter] string $token, SiteSettings $settings): void
    {
        $resetUrl = $this->urlGenerator->generate(
            'admin_reset_password',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFromEmail, $this->mailFromName))
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('Réinitialisation de votre mot de passe — '.$settings->getCompanyName())
            ->htmlTemplate('email/password_reset.html.twig')
            ->textTemplate('email/password_reset.txt.twig')
            ->context([
                'settings' => $settings,
                'user' => $user,
                'resetUrl' => $resetUrl,
            ]);

        try {
            $this->mailer->send($email);
        } catch (Throwable $exception) {
            $this->logger->error('Envoi de l’email de réinitialisation impossible.', [
                'error' => $exception::class,
            ]);
        }
    }

    public function findUserByToken(#[\SensitiveParameter] string $token): ?User
    {
        $user = $this->userRepository->findOneByResetToken(hash('sha256', $token));
        if (!$user instanceof User || !$user->isResetTokenValid()) {
            return null;
        }

        return $user;
    }

    public function resetPassword(User $user, #[\SensitiveParameter] string $plainPassword): void
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);
        $this->entityManager->flush();
    }
}
