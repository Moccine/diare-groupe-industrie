<?php

namespace App\Service;

use App\Controller\Admin\Crud\ContactRequestCrudController;
use App\Controller\Admin\Crud\HeroSlideCrudController;
use App\Controller\Admin\Crud\JobOfferCrudController;
use App\Controller\Admin\Crud\NewsCrudController;
use App\Controller\Admin\Crud\PageCrudController;
use App\Controller\Admin\Crud\PartnerCrudController;
use App\Controller\Admin\Crud\ProductCategoryCrudController;
use App\Controller\Admin\Crud\ProductCrudController;
use App\Controller\Admin\Crud\SectionCrudController;
use App\Controller\Admin\Crud\SiteSettingsCrudController;
use App\Entity\ContactRequest;
use App\Entity\SiteSettings;
use App\Repository\ContactRequestRepository;
use App\Repository\HeroSlideRepository;
use App\Repository\JobOfferRepository;
use App\Repository\NewsRepository;
use App\Repository\PageRepository;
use App\Repository\PartnerRepository;
use App\Repository\ProductCategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\SectionRepository;
use App\Repository\SiteSettingsRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;

final class DashboardOverview
{
    public function __construct(
        private readonly ContactRequestRepository $contactRequestRepository,
        private readonly ProductRepository $productRepository,
        private readonly NewsRepository $newsRepository,
        private readonly JobOfferRepository $jobOfferRepository,
        private readonly PartnerRepository $partnerRepository,
        private readonly PageRepository $pageRepository,
        private readonly SiteSettingsRepository $siteSettingsRepository,
        private readonly ProductCategoryRepository $productCategoryRepository,
        private readonly SectionRepository $sectionRepository,
        private readonly HeroSlideRepository $heroSlideRepository,
        private readonly AdminLinkFactory $adminLinks,
    ) {
    }

    /**
     * @return array{
     *     unreadMessages: int,
     *     totalMessages: int,
     *     publishedProducts: int,
     *     publishedNews: int,
     *     activeJobOffers: int,
     *     publishedPartners: int,
     *     publishedPages: int,
     *     recentMessages: list<ContactRequest>,
     *     gaps: list<array{label: string, url: string, action: string, type: string}>,
     *     emailConfigured: bool,
     *     mapConfigured: bool,
     *     logoConfigured: bool,
     *     settingsUrl: string
     * }
     */
    public function build(): array
    {
        $settings = $this->siteSettingsRepository->findSingleton();
        $emailConfigured = $settings?->hasPublicEmail() ?? false;
        $mapConfigured = $settings?->hasMap() ?? false;
        $logoConfigured = $settings?->getLogo() !== null;
        $settingsUrl = $this->settingsUrl($settings);

        $gaps = array_values(array_filter([
            $logoConfigured ? null : $this->notice('Le logo du site n’est pas configuré.', $settingsUrl),
            $emailConfigured ? null : $this->notice('L’adresse email de contact n’est pas renseignée.', $settingsUrl),
            $mapConfigured ? null : $this->notice('La carte du site n’a pas de coordonnées.', $settingsUrl),
            $this->countGap(
                $this->pageRepository->countPublishedWithoutVisibleSection(),
                'page publiée n’a aucun contenu visible.',
                'pages publiées n’ont aucun contenu visible.',
                $this->adminLinks->to(PageCrudController::class),
            ),
            $this->countGap(
                $this->productRepository->countPublishedWithoutImage(),
                'produit publié n’a pas d’image principale.',
                'produits publiés n’ont pas d’image principale.',
                $this->adminLinks->to(ProductCrudController::class),
            ),
            $this->countGap(
                $this->productRepository->countPublishedWithoutCategory(),
                'produit publié n’a pas de catégorie.',
                'produits publiés n’ont pas de catégorie.',
                $this->adminLinks->to(ProductCrudController::class),
            ),
            $this->countGap(
                $this->newsRepository->countPublishedWithoutImage(),
                'actualité publiée n’a pas d’image.',
                'actualités publiées n’ont pas d’image.',
                $this->adminLinks->to(NewsCrudController::class),
            ),
            $this->countGap(
                $this->sectionRepository->countVisibleHeroWithoutDisplay(),
                'bandeau est visible sans image ni bannière active.',
                'bandeaux sont visibles sans image ni bannière active.',
                $this->adminLinks->to(SectionCrudController::class),
            ),
            $this->countGap(
                $this->heroSlideRepository->countActiveWithoutImage(),
                'bannière active n’a pas d’image.',
                'bannières actives n’ont pas d’image.',
                $this->adminLinks->to(HeroSlideCrudController::class),
            ),
            $this->countGap(
                $this->jobOfferRepository->countOpenWithoutApplication(),
                'offre publiée n’a ni email ni lien de candidature.',
                'offres publiées n’ont ni email ni lien de candidature.',
                $this->adminLinks->to(JobOfferCrudController::class),
            ),
            $this->countGap(
                $this->jobOfferRepository->countExpiredStillPublished(),
                'offre est encore marquée publiée alors que sa date d’expiration est passée.',
                'offres sont encore marquées publiées alors que leur date d’expiration est passée.',
                $this->adminLinks->to(JobOfferCrudController::class),
            ),
            $this->countGap(
                $this->partnerRepository->countVisibleWithoutLogo(),
                'partenaire publié n’a pas de logo.',
                'partenaires publiés n’ont pas de logo.',
                $this->adminLinks->to(PartnerCrudController::class),
            ),
            $this->countGap(
                $this->productCategoryRepository->countPublishedWithoutProducts(),
                'catégorie publiée ne contient aucun produit.',
                'catégories publiées ne contiennent aucun produit.',
                $this->adminLinks->to(ProductCategoryCrudController::class),
                'info',
            ),
        ]));

        return [
            'unreadMessages' => $this->contactRequestRepository->countUnread(),
            'totalMessages' => $this->contactRequestRepository->countAll(),
            'publishedProducts' => $this->productRepository->countPublished(),
            'publishedNews' => $this->newsRepository->countPublished(),
            'activeJobOffers' => $this->jobOfferRepository->countOpen(),
            'publishedPartners' => $this->partnerRepository->countVisible(),
            'publishedPages' => $this->pageRepository->countPublished(),
            'recentMessages' => $this->contactRequestRepository->findRecent(5),
            'gaps' => $gaps,
            'emailConfigured' => $emailConfigured,
            'mapConfigured' => $mapConfigured,
            'logoConfigured' => $logoConfigured,
            'settingsUrl' => $settingsUrl,
        ];
    }

    private function settingsUrl(?SiteSettings $settings): string
    {
        $id = $settings?->getId();
        if ($id === null) {
            return $this->adminLinks->to(SiteSettingsCrudController::class, Action::INDEX);
        }

        return $this->adminLinks->to(SiteSettingsCrudController::class, Action::EDIT, $id);
    }

    /** @return array{label: string, url: string, action: string, type: string} */
    private function notice(string $label, string $url): array
    {
        return [
            'label' => $label,
            'url' => $url,
            'action' => 'Corriger',
            'type' => 'warning',
        ];
    }

    /** @return array{label: string, url: string, action: string, type: string}|null */
    private function countGap(int $count, string $singular, string $plural, string $url, string $type = 'warning'): ?array
    {
        if ($count < 1) {
            return null;
        }

        return [
            'label' => $count.' '.($count > 1 ? $plural : $singular),
            'url' => $url,
            'action' => $type === 'info' ? 'Voir' : 'Corriger',
            'type' => $type,
        ];
    }
}
