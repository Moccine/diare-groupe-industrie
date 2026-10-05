<?php

namespace App\Controller;

use App\Repository\JobOfferRepository;
use App\Repository\PageRepository;
use App\Service\PublicContent;
use App\Service\SeoFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class JobOfferController extends AbstractController
{
    public function __construct(
        private readonly JobOfferRepository $jobOfferRepository,
        private readonly PageRepository $pageRepository,
        private readonly PublicContent $publicContent,
        private readonly SeoFactory $seoFactory,
    ) {
    }

    #[Route('/nous-rejoindre', name: 'jobs', methods: ['GET'])]
    public function index(): Response
    {
        $page = $this->pageRepository->findPublishedBySlug('nous-rejoindre');

        return $this->render('page/jobs.html.twig', [
            'page' => $page,
            'sections' => $page ? $this->publicContent->visibleSections($page) : [],
            'jobs' => $this->jobOfferRepository->findOpen(),
            'seo' => $this->seoFactory->forPage($page, '/nous-rejoindre'),
            'header_overlay' => false,
        ] + $this->publicContent->sectionData());
    }

    #[Route('/nous-rejoindre/{slug}', name: 'job_show', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function show(string $slug): Response
    {
        $offer = $this->jobOfferRepository->findOpenBySlug($slug);
        if ($offer === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('page/job_show.html.twig', [
            'offer' => $offer,
            'seo' => $this->seoFactory->forJobOffer($offer),
            'header_overlay' => false,
        ] + $this->publicContent->sectionData());
    }
}
