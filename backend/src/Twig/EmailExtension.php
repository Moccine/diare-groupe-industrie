<?php

namespace App\Twig;

use App\Entity\SiteSettings;
use App\Service\EmailBranding;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class EmailExtension extends AbstractExtension
{
    public function __construct(
        private readonly EmailBranding $branding,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('email_logo', $this->logo(...)),
            new TwigFunction('email_site_url', $this->branding->siteUrl(...)),
            new TwigFunction('email_color', $this->branding->color(...)),
        ];
    }

    /** @return array{url: string, width: int, height: int}|null */
    public function logo(SiteSettings $settings): ?array
    {
        return $this->branding->logo($settings);
    }
}
