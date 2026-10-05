<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminUserProvisioner
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $adminEmail,
        private readonly string $adminPassword,
    ) {
    }

    public function provision(): User
    {
        $email = mb_strtolower(trim($this->adminEmail));
        if ($email === '' || $this->adminPassword === '') {
            throw new \RuntimeException('ADMIN_EMAIL et ADMIN_PASSWORD doivent être définis dans backend/.env.local.');
        }

        $user = $this->userRepository->findOneByEmail($email) ?? new User();
        $user->setEmail($email);
        if ($user->getFullName() === '') {
            $user->setFullName('Administrateur DGI');
        }
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $this->adminPassword));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
