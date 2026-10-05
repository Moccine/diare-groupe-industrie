<?php

namespace App\Repository;

use App\Entity\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Page> */
class PageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Page::class);
    }

    public function findPublishedBySlug(string $slug): ?Page
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.sections', 's')->addSelect('s')
            ->leftJoin('s.image', 'si')->addSelect('si')
            ->leftJoin('s.backgroundImage', 'sb')->addSelect('sb')
            ->leftJoin('s.items', 'it')->addSelect('it')
            ->leftJoin('it.image', 'iti')->addSelect('iti')
            ->leftJoin('s.slides', 'sl')->addSelect('sl')
            ->leftJoin('sl.image', 'sli')->addSelect('sli')
            ->leftJoin('sl.mobileImage', 'slm')->addSelect('slm')
            ->andWhere('p.slug = :slug')
            ->andWhere('p.isPublished = true')
            ->setParameter('slug', $slug)
            ->addOrderBy('s.position', 'ASC')
            ->addOrderBy('it.position', 'ASC')
            ->addOrderBy('sl.position', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<Page> */
    public function findForMenu(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = true')
            ->andWhere('p.showInMenu = true')
            ->orderBy('p.menuPosition', 'ASC')
            ->addOrderBy('p.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countPublished(): int
    {
        return $this->count(['isPublished' => true]);
    }

    public function countUnpublished(): int
    {
        return $this->count(['isPublished' => false]);
    }

    public function countPublishedWithoutVisibleSection(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.id)')
            ->leftJoin('p.sections', 's', 'WITH', 's.isVisible = true')
            ->andWhere('p.isPublished = true')
            ->andWhere('s.id IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<Page> */
    public function findPublished(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = true')
            ->orderBy('p.menuPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
