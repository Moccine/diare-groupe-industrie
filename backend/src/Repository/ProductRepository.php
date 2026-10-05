<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Product> */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /** @return list<Product> */
    public function findPublished(): array
    {
        return $this->findPublishedByCategorySlug(null);
    }

    /**
     * Produits publiés, éventuellement limités à une catégorie.
     * Un slug vide ou nul renvoie tout le catalogue publié.
     * Un slug inconnu renvoie une liste vide.
     *
     * @return list<Product>
     */
    public function findPublishedByCategorySlug(?string $categorySlug): array
    {
        $query = $this->publishedQuery();

        if ($categorySlug !== null && $categorySlug !== '') {
            $query
                ->andWhere('c.slug = :categorySlug')
                ->setParameter('categorySlug', $categorySlug);
        }

        return $query->getQuery()->getResult();
    }

    /** @return list<Product> */
    public function findFeatured(): array
    {
        return $this->publishedQuery()
            ->andWhere('p.isFeatured = true')
            ->addSelect('(CASE WHEN p.mainImage IS NULL THEN 1 ELSE 0 END) AS HIDDEN imageRank')
            ->orderBy('imageRank', 'ASC')
            ->addOrderBy('p.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Packagings de la bannière catalogue.
     * D’abord les produits publiés, mis en avant et illustrés,
     * puis les autres produits publiés illustrés.
     *
     * @return list<Product>
     */
    public function findForProductBanner(int $limit = 3): array
    {
        if ($limit < 1) {
            return [];
        }

        $featured = $this->publishedWithImageQuery()
            ->andWhere('p.isFeatured = true')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        if (count($featured) >= $limit) {
            return $featured;
        }

        $exclude = [];
        foreach ($featured as $product) {
            if ($product->getId() !== null) {
                $exclude[] = $product->getId();
            }
        }

        $query = $this->publishedWithImageQuery();
        if ($exclude !== []) {
            $query
                ->andWhere('p.id NOT IN (:exclude)')
                ->setParameter('exclude', $exclude);
        }

        $more = $query
            ->setMaxResults($limit - count($featured))
            ->getQuery()
            ->getResult();

        return array_merge($featured, $more);
    }

    public function countPublished(): int
    {
        return $this->count(['isPublished' => true]);
    }

    public function countUnpublished(): int
    {
        return $this->count(['isPublished' => false]);
    }

    public function countWithoutImage(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.mainImage IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPublishedWithoutImage(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.isPublished = true')
            ->andWhere('p.mainImage IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPublishedWithoutCategory(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.isPublished = true')
            ->andWhere('p.category IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Autres produits publiés, d’abord dans la même catégorie.
     *
     * @return list<Product>
     */
    public function findRelated(Product $product, int $limit = 3): array
    {
        if ($limit < 1 || $product->getId() === null) {
            return [];
        }

        $related = [];
        $category = $product->getCategory();
        if ($category !== null && $category->getId() !== null) {
            $related = $this->publishedQuery()
                ->andWhere('p.id != :id')
                ->andWhere('c.id = :categoryId')
                ->setParameter('id', $product->getId())
                ->setParameter('categoryId', $category->getId())
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        }

        if (count($related) >= $limit) {
            return $related;
        }

        $exclude = [$product->getId()];
        foreach ($related as $item) {
            if ($item->getId() !== null) {
                $exclude[] = $item->getId();
            }
        }

        $more = $this->publishedQuery()
            ->andWhere('p.id NOT IN (:exclude)')
            ->setParameter('exclude', $exclude)
            ->setMaxResults($limit - count($related))
            ->getQuery()
            ->getResult();

        return array_merge($related, $more);
    }

    public function findPublishedBySlug(string $slug): ?Product
    {
        return $this->publishedQuery()
            ->leftJoin('p.gallery', 'g')->addSelect('g')
            ->andWhere('p.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function publishedWithImageQuery(): \Doctrine\ORM\QueryBuilder
    {
        return $this->publishedQuery()
            ->andWhere('p.mainImage IS NOT NULL');
    }

    private function publishedQuery(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')->addSelect('c')
            ->leftJoin('p.mainImage', 'i')->addSelect('i')
            ->andWhere('p.isPublished = true')
            ->orderBy('p.position', 'ASC')
            ->addOrderBy('p.name', 'ASC');
    }
}
