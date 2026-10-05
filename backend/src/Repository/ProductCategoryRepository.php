<?php

namespace App\Repository;

use App\Entity\ProductCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ProductCategory> */
class ProductCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductCategory::class);
    }

    public function countPublishedWithoutProducts(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(DISTINCT c.id)')
            ->leftJoin('c.products', 'p')
            ->andWhere('c.isPublished = true')
            ->andWhere('p.id IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<ProductCategory> */
    public function findPublished(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isPublished = true')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
