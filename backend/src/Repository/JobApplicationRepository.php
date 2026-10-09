<?php

namespace App\Repository;

use App\Entity\JobApplication;
use App\Enum\JobApplicationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JobApplication> */
class JobApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobApplication::class);
    }

    public function countByStatus(JobApplicationStatus $status): int
    {
        return (int) $this->createQueryBuilder('application')
            ->select('COUNT(application.id)')
            ->andWhere('application.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findRecentDuplicate(
        string $email,
        ?int $jobOfferId,
        bool $spontaneous,
        ?string $desiredRole,
        \DateTimeImmutable $since,
    ): ?JobApplication {
        $query = $this->createQueryBuilder('application')
            ->andWhere('LOWER(application.email) = :email')
            ->andWhere('application.createdAt >= :since')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->setParameter('since', $since)
            ->setMaxResults(1);

        if ($spontaneous) {
            $query
                ->andWhere('application.isSpontaneous = true')
                ->andWhere('application.desiredRole = :role')
                ->setParameter('role', $desiredRole);
        } else {
            $query
                ->andWhere('application.jobOffer = :offer')
                ->setParameter('offer', $jobOfferId);
        }

        return $query->getQuery()->getOneOrNullResult();
    }

    /** @return list<JobApplication> */
    public function findReadyForPurge(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('application')
            ->andWhere('application.retentionUntil IS NOT NULL')
            ->andWhere('application.retentionUntil <= :now')
            ->setParameter('now', $now)
            ->orderBy('application.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
