<?php

namespace App\Repository;

use App\Entity\ContactRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ContactRequest> */
class ContactRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactRequest::class);
    }

    public function findRecentDuplicate(
        string $email,
        string $subject,
        string $message,
        \DateTimeImmutable $since,
    ): ?ContactRequest {
        return $this->createQueryBuilder('request')
            ->andWhere('LOWER(request.email) = :email')
            ->andWhere('request.subject = :subject')
            ->andWhere('request.message = :message')
            ->andWhere('request.createdAt >= :since')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->setParameter('subject', trim($subject))
            ->setParameter('message', trim($message))
            ->setParameter('since', $since)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countUnread(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.isRead = false')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<ContactRequest> */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
