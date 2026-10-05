<?php

namespace App\Repository;

use App\Entity\Section;
use App\Enum\SectionType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Section> */
class SectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Section::class);
    }

    public function countVisibleHeroWithoutDisplay(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(DISTINCT s.id)')
            ->leftJoin('s.slides', 'sl', 'WITH', 'sl.isActive = true AND sl.image IS NOT NULL')
            ->andWhere('s.type = :type')
            ->andWhere('s.isVisible = true')
            ->andWhere('s.image IS NULL')
            ->andWhere('sl.id IS NULL')
            ->setParameter('type', SectionType::Hero)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
