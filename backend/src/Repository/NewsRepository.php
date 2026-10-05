<?php

namespace App\Repository;

use App\Entity\News;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<News> */
class NewsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, News::class);
    }

    /** @return list<News> */
    public function findPublished(?int $limit = null): array
    {
        $qb = $this->publishedQuery();

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function countPublished(): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.isPublished = true')
            ->andWhere('n.publishedAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countWithoutImage(): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.image IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPublishedWithoutImage(): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.isPublished = true')
            ->andWhere('n.image IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findPublishedBySlug(string $slug): ?News
    {
        return $this->publishedQuery()
            ->andWhere('n.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function publishedQuery(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.image', 'i')->addSelect('i')
            ->andWhere('n.isPublished = true')
            ->andWhere('n.publishedAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('n.publishedAt', 'DESC');
    }
}
