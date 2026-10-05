<?php

namespace App\Repository;

use App\Entity\Partner;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Partner> */
class PartnerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Partner::class);
    }

    public function countVisible(): int
    {
        return $this->count(['isVisible' => true]);
    }

    public function countVisibleWithoutLogo(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.isVisible = true')
            ->andWhere('p.logo IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<Partner> */
    public function findVisible(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.logo', 'l')->addSelect('l')
            ->andWhere('p.isVisible = true')
            ->orderBy('p.position', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
