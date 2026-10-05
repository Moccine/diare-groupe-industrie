<?php

namespace App\Repository;

use App\Entity\Statistic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Statistic> */
class StatisticRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Statistic::class);
    }

    /** @return list<Statistic> */
    public function findVisible(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.isVisible = true')
            ->orderBy('s.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
