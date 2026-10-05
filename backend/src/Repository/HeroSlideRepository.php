<?php

namespace App\Repository;

use App\Entity\HeroSlide;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<HeroSlide> */
class HeroSlideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HeroSlide::class);
    }

    public function countActiveWithoutImage(): int
    {
        return (int) $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->andWhere('h.isActive = true')
            ->andWhere('h.image IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
