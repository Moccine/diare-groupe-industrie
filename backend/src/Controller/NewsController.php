<?php

namespace App\Controller;

use App\Enum\SectionType;
use App\Repository\NewsRepository;
use App\Repository\PageRepository;
use App\Service\PublicContent;
use App\Service\SeoFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NewsController extends AbstractController
{
    public function __construct(
        private readonly NewsRepository $newsRepository,
        private readonly PageRepository $pageRepository,
        private readonly PublicContent $publicContent,
        private readonly SeoFactory $seoFactory,
    ) {
    }

    #[Route('/actualites', name: 'news', methods: ['GET'])]
    public function index(): Response
    {
        $page = $this->pageRepository->findPublishedBySlug('actualites');

        return $this->render('page/news.html.twig', [
            'page' => $page,
            'sections' => $page ? $this->publicContent->visibleSections($page, [SectionType::News]) : [],
            'news_list' => $this->newsRepository->findPublished(),
            'seo' => $this->seoFactory->forPage($page, '/actualites'),
            'header_overlay' => false,
        ] + $this->publicContent->sectionData());
    }

    #[Route('/actualites/{slug}', name: 'news_show', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function show(string $slug): Response
    {
        $article = $this->newsRepository->findPublishedBySlug($slug);
        if ($article === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('page/news_show.html.twig', [
            'article' => $article,
            'seo' => $this->seoFactory->forNews($article),
            'header_overlay' => false,
        ] + $this->publicContent->sectionData());
    }
}
