<?php

namespace App\Controller;

use App\Repository\JobOfferRepository;
use App\Repository\NewsRepository;
use App\Repository\PageRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SeoController extends AbstractController
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly ProductRepository $productRepository,
        private readonly NewsRepository $newsRepository,
        private readonly JobOfferRepository $jobOfferRepository,
        private readonly bool $appIndexable,
    ) {
    }

    #[Route('/robots.txt', name: 'robots', methods: ['GET'])]
    public function robots(): Response
    {
        if (!$this->appIndexable) {
            $body = "User-agent: *\nDisallow: /\n";

            return new Response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $sitemap = $this->generateUrl('sitemap', [], 0);
        $body = "User-agent: *\nAllow: /\nDisallow: /administration\nDisallow: /nous-rejoindre/candidature-spontanee/confirmation\nDisallow: /nous-rejoindre/*/postuler/confirmation\n\nSitemap: ".$sitemap."\n";

        return new Response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    #[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'])]
    public function sitemap(): Response
    {
        $urls = [
            ['loc' => $this->generateUrl('home', [], 0), 'lastmod' => null],
        ];

        $handledByDedicatedRoutes = ['accueil', 'nos-produits', 'actualites', 'contact', 'nous-rejoindre'];
        foreach ($this->pageRepository->findPublished() as $page) {
            if (in_array($page->getSlug(), $handledByDedicatedRoutes, true)) {
                continue;
            }
            $urls[] = [
                'loc' => $this->generateUrl('page_show', ['slug' => $page->getSlug()], 0),
                'lastmod' => $page->getUpdatedAt(),
            ];
        }

        $urls[] = ['loc' => $this->generateUrl('products', [], 0), 'lastmod' => null];
        foreach ($this->productRepository->findPublished() as $product) {
            $urls[] = [
                'loc' => $this->generateUrl('product_show', ['slug' => $product->getSlug()], 0),
                'lastmod' => $product->getUpdatedAt(),
            ];
        }

        $urls[] = ['loc' => $this->generateUrl('news', [], 0), 'lastmod' => null];
        foreach ($this->newsRepository->findPublished() as $article) {
            $urls[] = [
                'loc' => $this->generateUrl('news_show', ['slug' => $article->getSlug()], 0),
                'lastmod' => $article->getUpdatedAt(),
            ];
        }

        $urls[] = ['loc' => $this->generateUrl('jobs', [], 0), 'lastmod' => null];
        foreach ($this->jobOfferRepository->findOpen() as $offer) {
            $urls[] = [
                'loc' => $this->generateUrl('job_show', ['slug' => $offer->getSlug()], 0),
                'lastmod' => $offer->getUpdatedAt(),
            ];
        }

        $urls[] = ['loc' => $this->generateUrl('contact', [], 0), 'lastmod' => null];

        $response = $this->render('seo/sitemap.xml.twig', ['urls' => $urls]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }
}
