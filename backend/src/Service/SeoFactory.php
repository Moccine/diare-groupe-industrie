<?php

namespace App\Service;

use App\Entity\JobOffer;
use App\Entity\News;
use App\Entity\Page;
use App\Entity\Product;

final class SeoFactory
{
    public function __construct(
        private readonly PublicContent $publicContent,
    ) {
    }

    public function forPage(?Page $page, string $canonicalPath, ?string $imagePath = null): SeoDocument
    {
        $settings = $this->publicContent->settings();
        $title = $this->firstText($page?->getMetaTitle(), $page?->getTitle(), $settings->getMetaTitle(), $settings->getCompanyName());
        $description = $this->firstText($page?->getMetaDescription(), $settings->getMetaDescription(), $settings->getCompanyName());

        return new SeoDocument($title, $description, $canonicalPath, $imagePath);
    }

    public function forProduct(Product $product): SeoDocument
    {
        $settings = $this->publicContent->settings();
        $title = $this->firstText($product->getMetaTitle(), $product->getName().' — '.$settings->getCompanyName());
        $description = $this->firstText($product->getMetaDescription(), $product->getShortDescription(), $product->getDescription(), $settings->getCompanyName());

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->getName(),
            'description' => $description,
            'brand' => [
                '@type' => 'Brand',
                'name' => $settings->getCompanyName(),
            ],
        ];
        if ($product->getMainImage() !== null) {
            $data['image'] = $product->getMainImage()->getPublicPath();
        }

        return new SeoDocument(
            $title,
            $description,
            '/nos-produits/'.$product->getSlug(),
            $product->getMainImage()?->getPublicPath(),
            'product',
            $this->encode($data),
        );
    }

    public function forNews(News $news): SeoDocument
    {
        $settings = $this->publicContent->settings();
        $title = $this->firstText($news->getMetaTitle(), $news->getTitle().' — '.$settings->getCompanyName());
        $description = $this->firstText($news->getMetaDescription(), $news->getExcerpt(), $settings->getCompanyName());
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $news->getTitle(),
            'datePublished' => $news->getPublishedAt()->format(\DateTimeInterface::ATOM),
            'dateModified' => $news->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'author' => [
                '@type' => 'Organization',
                'name' => $settings->getCompanyName(),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $settings->getCompanyName(),
            ],
        ];
        if ($description !== '') {
            $data['description'] = $description;
        }
        if ($news->getImage() !== null) {
            $data['image'] = $news->getImage()->getPublicPath();
        }

        return new SeoDocument(
            $title,
            $description,
            '/actualites/'.$news->getSlug(),
            $news->getImage()?->getPublicPath(),
            'article',
            $this->encode($data),
        );
    }

    public function forJobOffer(JobOffer $offer): SeoDocument
    {
        $settings = $this->publicContent->settings();
        $title = $offer->getTitle().' — '.$settings->getCompanyName();
        $description = $this->firstText($offer->getShortDescription(), $offer->getDescription(), $settings->getCompanyName());
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => $offer->getTitle(),
            'datePosted' => $offer->getPublishedAt()->format(\DateTimeInterface::ATOM),
            'employmentType' => $offer->getContractType()->label(),
            'hiringOrganization' => [
                '@type' => 'Organization',
                'name' => $settings->getCompanyName(),
            ],
        ];
        if ($description !== '') {
            $data['description'] = $description;
        }
        if ($offer->getExpiresAt() !== null) {
            $data['validThrough'] = $offer->getExpiresAt()->format(\DateTimeInterface::ATOM);
        }
        if ($offer->getLocation() !== null) {
            $data['jobLocation'] = [
                '@type' => 'Place',
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => $offer->getLocation(),
                    'addressCountry' => 'GN',
                ],
            ];
        }

        return new SeoDocument(
            $title,
            $description,
            '/nous-rejoindre/'.$offer->getSlug(),
            null,
            'website',
            $this->encode($data),
        );
    }

    public function forJobApplication(?JobOffer $offer, bool $confirmation, ?string $slug = null): SeoDocument
    {
        $settings = $this->publicContent->settings();
        $company = $settings->getCompanyName();
        if ($offer !== null) {
            $title = ($confirmation ? 'Candidature envoyée' : 'Postuler').' — '.$offer->getTitle();
            $path = '/nous-rejoindre/'.$offer->getSlug().'/postuler'.($confirmation ? '/confirmation' : '');
            $description = 'Candidature au poste '.$offer->getTitle().' chez '.$company.'.';
        } elseif ($slug !== null) {
            $title = 'Candidature envoyée — '.$company;
            $path = '/nous-rejoindre/'.$slug.'/postuler/confirmation';
            $description = 'Confirmation de candidature.';
        } else {
            $title = ($confirmation ? 'Candidature envoyée' : 'Candidature spontanée').' — '.$company;
            $path = '/nous-rejoindre/candidature-spontanee'.($confirmation ? '/confirmation' : '');
            $description = 'Déposer une candidature spontanée auprès de '.$company.'.';
        }

        return new SeoDocument($title, $description, $path, null, 'website', null, $confirmation);
    }

    public function forClosedJobApplication(): SeoDocument
    {
        $company = $this->publicContent->settings()->getCompanyName();

        return new SeoDocument(
            'Offre indisponible — '.$company,
            'Cette offre n’accepte plus de candidatures.',
            '/nous-rejoindre',
            null,
            'website',
            null,
            true,
        );
    }

    public function organizationJson(string $siteUrl): string
    {
        $settings = $this->publicContent->settings();
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $settings->getCompanyName(),
            'url' => $siteUrl,
        ];

        if ($settings->getPhone()) {
            $data['telephone'] = $settings->getPhone();
        }
        if ($settings->hasPublicEmail()) {
            $data['email'] = $settings->getEmail();
        }
        if ($settings->getAddress()) {
            $data['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $settings->getAddress(),
                'addressCountry' => 'GN',
            ];
        }
        if ($settings->getLogo() !== null) {
            $data['logo'] = rtrim($siteUrl, '/').$settings->getLogo()->getPublicPath();
        }
        $sameAs = array_values($settings->getSocialLinks());
        if ($sameAs !== []) {
            $data['sameAs'] = $sameAs;
        }

        return $this->encode($data);
    }

    private function firstText(?string ...$values): string
    {
        foreach ($values as $value) {
            $text = trim((string) $value);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    /** @param array<string, mixed> $data */
    private function encode(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
