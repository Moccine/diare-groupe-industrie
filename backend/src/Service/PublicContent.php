<?php

namespace App\Service;

use App\Entity\Page;
use App\Entity\Section;
use App\Entity\SiteSettings;
use App\Enum\SectionType;
use App\Repository\NewsRepository;
use App\Repository\PageRepository;
use App\Repository\PartnerRepository;
use App\Repository\ProductRepository;
use App\Repository\SiteSettingsRepository;
use App\Repository\StatisticRepository;

final class PublicContent
{
    private ?SiteSettings $settings = null;
    private bool $settingsLoaded = false;

    public function __construct(
        private readonly SiteSettingsRepository $settingsRepository,
        private readonly PageRepository $pageRepository,
        private readonly ProductRepository $productRepository,
        private readonly NewsRepository $newsRepository,
        private readonly StatisticRepository $statisticRepository,
        private readonly PartnerRepository $partnerRepository,
        private readonly SectionTemplateResolver $sectionTemplateResolver,
    ) {
    }

    public function settings(): SiteSettings
    {
        if (!$this->settingsLoaded) {
            $this->settings = $this->settingsRepository->findSingleton();
            $this->settingsLoaded = true;
        }

        return $this->settings ?? SiteSettings::fallback();
    }

    /** @return list<Page> */
    public function menuPages(): array
    {
        return $this->pageRepository->findForMenu();
    }

    /**
     * @param list<SectionType> $excluded
     *
     * @return list<array{template: string, section: Section}>
     */
    public function visibleSections(Page $page, array $excluded = []): array
    {
        $excludedValues = array_map(static fn (SectionType $type): string => $type->value, $excluded);
        $blocks = [];

        foreach ($page->getSections() as $section) {
            if (!$section->isVisible() || in_array($section->getType()->value, $excludedValues, true)) {
                continue;
            }

            $template = $this->sectionTemplateResolver->resolve($section->getType());
            if ($template === null) {
                continue;
            }

            $blocks[] = [
                'template' => $template,
                'section' => $section,
            ];
        }

        return $blocks;
    }

    /** @return array<string, mixed> */
    public function sectionData(): array
    {
        return [
            'featured_products' => $this->productRepository->findFeatured(),
            'statistics' => $this->statisticRepository->findVisible(),
            'latest_news' => $this->newsRepository->findPublished(3),
            'partners' => $this->partnerRepository->findVisible(),
        ];
    }
}
