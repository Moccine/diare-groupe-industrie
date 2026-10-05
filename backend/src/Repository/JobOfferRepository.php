<?php

namespace App\Repository;

use App\Entity\JobOffer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JobOffer> */
class JobOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobOffer::class);
    }

    /** @return list<JobOffer> */
    public function findOpen(): array
    {
        return $this->openQuery()
            ->getQuery()
            ->getResult();
    }

    public function countOpenWithoutApplication(): int
    {
        return (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.isPublished = true')
            ->andWhere('j.publishedAt <= :now')
            ->andWhere('j.expiresAt IS NULL OR j.expiresAt > :now')
            ->andWhere('j.applicationUrl IS NULL OR j.applicationUrl = \'\'')
            ->andWhere('j.applicationEmail IS NULL OR j.applicationEmail = \'\'')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countExpiredStillPublished(): int
    {
        return (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.isPublished = true')
            ->andWhere('j.expiresAt IS NOT NULL')
            ->andWhere('j.expiresAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countOpen(): int
    {
        return (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.isPublished = true')
            ->andWhere('j.publishedAt <= :now')
            ->andWhere('j.expiresAt IS NULL OR j.expiresAt > :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOpenBySlug(string $slug): ?JobOffer
    {
        return $this->openQuery()
            ->andWhere('j.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function openQuery(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.isPublished = true')
            ->andWhere('j.publishedAt <= :now')
            ->andWhere('j.expiresAt IS NULL OR j.expiresAt > :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('j.publishedAt', 'DESC')
            ->addOrderBy('j.id', 'DESC');
    }
}
