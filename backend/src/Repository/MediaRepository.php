<?php

namespace App\Repository;

use App\Entity\HeroSlide;
use App\Entity\Media;
use App\Entity\News;
use App\Entity\Partner;
use App\Entity\Product;
use App\Entity\Section;
use App\Entity\SectionItem;
use App\Entity\SiteSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Media> */
class MediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Media::class);
    }

    /** @return list<string> */
    public function usageLabels(Media $media): array
    {
        if ($media->getId() === null) {
            return [];
        }

        $labels = [];
        $manager = $this->getEntityManager();

        $products = $manager->createQueryBuilder()
            ->select('DISTINCT p.name')
            ->from(Product::class, 'p')
            ->leftJoin('p.gallery', 'g')
            ->where('p.mainImage = :media OR g = :media')
            ->setParameter('media', $media)
            ->getQuery()
            ->getSingleColumnResult();
        foreach ($products as $name) {
            $labels[] = 'Produit « '.$name.' »';
        }

        $articles = $manager->createQueryBuilder()
            ->select('n.title')
            ->from(News::class, 'n')
            ->where('n.image = :media')
            ->setParameter('media', $media)
            ->getQuery()
            ->getSingleColumnResult();
        foreach ($articles as $title) {
            $labels[] = 'Actualité « '.$title.' »';
        }

        $partners = $manager->createQueryBuilder()
            ->select('p.name')
            ->from(Partner::class, 'p')
            ->where('p.logo = :media')
            ->setParameter('media', $media)
            ->getQuery()
            ->getSingleColumnResult();
        foreach ($partners as $name) {
            $labels[] = 'Partenaire « '.$name.' »';
        }

        /** @var list<array{title: ?string, pageTitle: string}> $sections */
        $sections = $manager->createQueryBuilder()
            ->select('s.title AS title', 'pg.title AS pageTitle')
            ->from(Section::class, 's')
            ->join('s.page', 'pg')
            ->where('s.image = :media OR s.backgroundImage = :media')
            ->setParameter('media', $media)
            ->getQuery()
            ->getArrayResult();
        foreach ($sections as $section) {
            $title = trim((string) ($section['title'] ?? ''));
            $labels[] = $title !== ''
                ? 'Contenu « '.$title.' » de la page « '.$section['pageTitle'].' »'
                : 'Contenu de la page « '.$section['pageTitle'].' »';
        }

        $slides = $manager->createQueryBuilder()
            ->select('h.title')
            ->from(HeroSlide::class, 'h')
            ->where('h.image = :media OR h.mobileImage = :media')
            ->setParameter('media', $media)
            ->getQuery()
            ->getSingleColumnResult();
        foreach ($slides as $title) {
            $labels[] = 'Bannière « '.$title.' »';
        }

        /** @var list<array{title: string, pageTitle: string}> $items */
        $items = $manager->createQueryBuilder()
            ->select('i.title AS title', 'pg.title AS pageTitle')
            ->from(SectionItem::class, 'i')
            ->join('i.section', 's')
            ->join('s.page', 'pg')
            ->where('i.image = :media')
            ->setParameter('media', $media)
            ->getQuery()
            ->getArrayResult();
        foreach ($items as $item) {
            $labels[] = 'Ligne « '.$item['title'].' » de la page « '.$item['pageTitle'].' »';
        }

        $settingsRepository = $manager->getRepository(SiteSettings::class);
        $settings = $settingsRepository instanceof SiteSettingsRepository ? $settingsRepository->findSingleton() : null;
        if ($settings instanceof SiteSettings) {
            if ($settings->getLogo()?->getId() === $media->getId()) {
                $labels[] = 'Logo du site';
            }
            if ($settings->getFavicon()?->getId() === $media->getId()) {
                $labels[] = 'Icône de l’onglet du navigateur';
            }
        }

        return $labels;
    }
}
