<?php

namespace App\Controller;

use App\Repository\PageRepository;
use App\Service\PublicContent;
use App\Service\SeoFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageController extends AbstractController
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly PublicContent $publicContent,
        private readonly SeoFactory $seoFactory,
    ) {
    }

    #[Route('/{slug}', name: 'page_show', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'], priority: -10)]
    public function show(string $slug): Response
    {
        if ($slug === 'accueil') {
            return $this->redirectToRoute('home', [], 301);
        }

        $page = $this->pageRepository->findPublishedBySlug($slug);
        if ($page === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('page/dynamic.html.twig', [
            'page' => $page,
            'sections' => $this->publicContent->visibleSections($page),
            'seo' => $this->seoFactory->forPage($page, '/'.$slug),
            'header_overlay' => false,
        ] + $this->publicContent->sectionData());
    }
}
