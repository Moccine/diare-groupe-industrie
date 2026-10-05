<?php

namespace App\Controller;

use App\Enum\SectionType;
use App\Repository\PageRepository;
use App\Repository\ProductCategoryRepository;
use App\Repository\ProductRepository;
use App\Service\PublicContent;
use App\Service\SeoFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductCategoryRepository $productCategoryRepository,
        private readonly PageRepository $pageRepository,
        private readonly PublicContent $publicContent,
        private readonly SeoFactory $seoFactory,
    ) {
    }

    #[Route('/nos-produits', name: 'products', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $categorySlug = $request->query->getString('categorie');
        $products = $this->productRepository->findPublishedByCategorySlug($categorySlug !== '' ? $categorySlug : null);

        if ($request->isXmlHttpRequest()) {
            return $this->render('product/_catalog_results.html.twig', [
                'products' => $products,
                'active_category' => $categorySlug,
            ]);
        }

        $page = $this->pageRepository->findPublishedBySlug('nos-produits');

        return $this->render('page/products.html.twig', [
            'page' => $page,
            'sections' => $page ? $this->publicContent->visibleSections($page, [SectionType::Products]) : [],
            'products' => $products,
            'banner_products' => $this->productRepository->findForProductBanner(3),
            'categories' => $this->productCategoryRepository->findPublished(),
            'active_category' => $categorySlug,
            'seo' => $this->seoFactory->forPage($page, '/nos-produits'),
            'header_overlay' => false,
        ] + $this->publicContent->sectionData());
    }

    #[Route('/nos-produits/{slug}', name: 'product_show', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function show(string $slug): Response
    {
        $product = $this->productRepository->findPublishedBySlug($slug);
        if ($product === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('page/product_show.html.twig', [
            'product' => $product,
            'related_products' => $this->productRepository->findRelated($product),
            'seo' => $this->seoFactory->forProduct($product),
            'header_overlay' => false,
        ] + $this->publicContent->sectionData());
    }
}
