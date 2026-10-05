<?php

namespace App\Twig;

use App\Entity\Page;
use App\Entity\SiteSettings;
use App\Service\PublicContent;
use App\Service\SeoFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly PublicContent $publicContent,
        private readonly SeoFactory $seoFactory,
        private readonly RequestStack $requestStack,
        private readonly string $defaultUri,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('site_settings', $this->settings(...)),
            new TwigFunction('menu_pages', $this->menuPages(...)),
            new TwigFunction('organization_json', $this->organizationJson(...)),
        ];
    }

    public function organizationJson(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $origin = $request ? $request->getSchemeAndHttpHost() : $this->defaultUri;

        return $this->seoFactory->organizationJson($origin);
    }

    public function settings(): SiteSettings
    {
        return $this->publicContent->settings();
    }

    /** @return list<Page> */
    public function menuPages(): array
    {
        return $this->publicContent->menuPages();
    }
}
