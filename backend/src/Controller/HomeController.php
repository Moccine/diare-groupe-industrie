<?php

namespace App\Controller;

use App\Repository\PageRepository;
use App\Service\PublicContent;
use App\Service\SeoFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly PublicContent $publicContent,
        private readonly SeoFactory $seoFactory,
    ) {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(): Response
    {
        $page = $this->pageRepository->findPublishedBySlug('accueil');
        if ($page === null) {
            throw $this->createNotFoundException('La page d’accueil n’est pas publiée.');
        }

        return $this->render('page/dynamic.html.twig', [
            'page' => $page,
            'sections' => $this->publicContent->visibleSections($page),
            'seo' => $this->seoFactory->forPage($page, '/'),
            'header_overlay' => true,
        ] + $this->publicContent->sectionData());
    }
}
