<?php

namespace App\Tests;

use App\Entity\Media;
use App\Entity\Page;
use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Enum\CategoryAccent;
use App\Enum\CategoryIcon;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProductCatalogTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite est requis pour le catalogue de test.');
        }

        $databaseUrl = 'sqlite:///'.dirname(__DIR__).'/var/catalog_phpunit.db';
        putenv('APP_ENV=test');
        putenv('DATABASE_URL='.$databaseUrl);
        $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $databaseUrl;

        $this->client = static::createClient();
        $params = $this->manager()->getConnection()->getParams();
        if (($params['driver'] ?? '') !== 'pdo_sqlite') {
            self::markTestSkipped('Le test catalogue refuse de s’exécuter hors SQLite.');
        }
        $manager = $this->manager();
        $tool = new SchemaTool($manager);
        $metadata = $manager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $lait = $this->category('Lait', 'lait', CategoryIcon::Milk, 1);
        $biscuits = $this->category('Biscuits', 'biscuits', CategoryIcon::Biscuit, 2);
        $manager->persist((new Page())->setTitle('Nos produits')->setSlug('nos-produits')->setIsPublished(true));
        $manager->persist($lait);
        $manager->persist($biscuits);
        $manager->persist($this->product('Lait frais', 'lait-frais', $lait, true));
        $manager->persist($this->product('Lait masqué', 'lait-masque', $lait, false));
        $manager->persist($this->product('Biscuit ABC', 'biscuit-abc', $biscuits, true));
        $manager->flush();
    }

    public function testCategoryFilterKeepsOnlyPublishedProducts(): void
    {
        $this->client->request('GET', '/nos-produits?categorie=lait');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Lait frais', $content);
        self::assertStringNotContainsString('Biscuit ABC', $content);
        self::assertStringNotContainsString('Lait masqué', $content);
        self::assertStringContainsString('aria-current="true"', $content);
        self::assertStringContainsString('href="/nos-produits?categorie=lait"', $content);
    }

    public function testUnknownCategoryRendersAnEmptyCatalog(): void
    {
        $this->client->request('GET', '/nos-produits?categorie=inexistante');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Aucun produit dans cette catégorie', $content);
        self::assertStringContainsString('Voir tous les produits', $content);
        self::assertStringNotContainsString('Lait frais', $content);
        self::assertStringNotContainsString('Biscuit ABC', $content);
    }

    public function testProductPageSuggestsAnotherPublishedProduct(): void
    {
        $this->client->request('GET', '/nos-produits/lait-frais');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Découvrir aussi', $content);
        self::assertStringContainsString('Biscuit ABC', $content);
        self::assertStringNotContainsString('Lait masqué', $content);
        self::assertStringNotContainsString('page-intro', $content);
        self::assertStringContainsString('media-placeholder', $content);
        self::assertStringNotContainsString('data-product-gallery', $content);
        self::assertStringContainsString('href="/contact?produit=lait-frais"', $content);
        self::assertStringContainsString('Demander des informations sur ce produit', $content);
        self::assertSame(1, substr_count($content, '<h1'));
    }

    public function testProductGalleryKeepsASingleCopyOfTheMainImage(): void
    {
        $manager = $this->manager();
        $lait = $manager->getRepository(ProductCategory::class)->findOneBy(['slug' => 'lait']);
        self::assertInstanceOf(ProductCategory::class, $lait);

        $cover = $this->media('cover.webp');
        $side = $this->media('side.webp');
        $manager->persist($cover);
        $manager->persist($side);
        $product = $this->product('Pack photo', 'pack-photo', $lait, true)
            ->setMainImage($cover)
            ->setShortDescription('Résumé visible.')
            ->setDescription('Résumé visible.');
        $product->addGallery($cover);
        $product->addGallery($side);
        $manager->persist($product);
        $manager->flush();

        $this->client->request('GET', '/nos-produits/pack-photo');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertSame(1, substr_count($content, 'data-src="/uploads/media/cover.webp"'));
        self::assertSame(1, substr_count($content, 'data-src="/uploads/media/side.webp"'));
        self::assertSame(1, substr_count($content, ' src="/uploads/media/cover.webp"'));
        self::assertStringContainsString('src="/uploads/media/thumbnails/side.webp"', $content);
        self::assertSame(0, substr_count($content, ' src="/uploads/media/side.webp"'));
        self::assertStringContainsString('fetchpriority="high"', $content);
        self::assertStringContainsString('class="product-sheet__lead">Résumé visible.</p>', $content);
        self::assertStringNotContainsString('product-sheet__description', $content);
        self::assertStringContainsString('href="/nos-produits?categorie=lait"', $content);
    }

    public function testProductPageShowsDistinctDescriptionInTheInfoColumn(): void
    {
        $manager = $this->manager();
        $lait = $manager->getRepository(ProductCategory::class)->findOneBy(['slug' => 'lait']);
        self::assertInstanceOf(ProductCategory::class, $lait);
        $product = $manager->getRepository(Product::class)->findOneBy(['slug' => 'lait-frais']);
        self::assertInstanceOf(Product::class, $product);
        $product->setShortDescription('Résumé de la fiche.');
        $product->setDescription("Détail de fabrication.\nSeconde ligne.");
        $manager->flush();

        $this->client->request('GET', '/nos-produits/lait-frais');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Résumé de la fiche.', $content);
        self::assertStringContainsString('<h2>Description</h2>', $content);
        self::assertStringContainsString('Détail de fabrication.', $content);
        self::assertStringContainsString('<br', $content);
        $info = strpos($content, 'class="product-sheet__info"');
        $summary = strpos($content, 'class="product-sheet__lead">Résumé de la fiche.</p>');
        $description = strpos($content, 'class="product-sheet__description"');
        $contact = strpos($content, 'Demander des informations sur ce produit');
        $back = strpos($content, 'class="product-sheet__back"');
        self::assertNotFalse($info);
        self::assertNotFalse($summary);
        self::assertNotFalse($description);
        self::assertNotFalse($contact);
        self::assertNotFalse($back);
        self::assertLessThan($summary, $info);
        self::assertLessThan($description, $summary);
        self::assertLessThan($contact, $description);
        self::assertLessThan($back, $contact);
    }

    public function testLargeGalleryExposesEveryPhotoWithoutLoadingEachOriginal(): void
    {
        $manager = $this->manager();
        $biscuits = $manager->getRepository(ProductCategory::class)->findOneBy(['slug' => 'biscuits']);
        self::assertInstanceOf(ProductCategory::class, $biscuits);
        $product = $this->product('Gamme complète', 'gamme-complete', $biscuits, true);
        for ($index = 1; $index <= 50; ++$index) {
            $media = $this->media(sprintf('shot-%02d.webp', $index));
            $manager->persist($media);
            $product->addGallery($media);
        }
        $manager->persist($product);
        $manager->flush();

        $this->client->request('GET', '/nos-produits/gamme-complete');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertSame(50, substr_count($content, 'data-product-gallery-thumb') - substr_count($content, 'data-product-gallery-thumbs'));
        self::assertSame(1, substr_count($content, ' src="/uploads/media/shot-01.webp"'));
        self::assertSame(0, substr_count($content, ' src="/uploads/media/shot-02.webp"'));
        self::assertStringContainsString('data-src="/uploads/media/shot-50.webp"', $content);
        self::assertStringContainsString('src="/uploads/media/thumbnails/shot-50.webp"', $content);
        self::assertStringContainsString('loading="lazy"', $content);
    }

    public function testAjaxReturnsOnlyTheCatalogResults(): void
    {
        $this->client->request('GET', '/nos-produits?categorie=biscuits', [], [], [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('catalog-results__inner', $content);
        self::assertStringContainsString('Biscuit ABC', $content);
        self::assertStringNotContainsString('Lait frais', $content);
        self::assertStringNotContainsString('catalog-filters', $content);
        self::assertStringNotContainsString('page-intro__stage', $content);
        self::assertStringNotContainsString('<html', $content);
    }

    public function testProductBannerStaysEmptyWithoutImages(): void
    {
        $this->client->request('GET', '/nos-produits');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('page-intro--nos-produits', $content);
        self::assertStringNotContainsString('page-intro__stage', $content);
        self::assertStringContainsString('packaging', $content);
    }

    public function testProductBannerPrefersFeaturedPackshots(): void
    {
        $manager = $this->manager();
        $lait = $manager->getRepository(ProductCategory::class)->findOneBy(['slug' => 'lait']);
        $biscuits = $manager->getRepository(ProductCategory::class)->findOneBy(['slug' => 'biscuits']);
        self::assertInstanceOf(ProductCategory::class, $lait);
        self::assertInstanceOf(ProductCategory::class, $biscuits);

        $alpha = $this->media('alpha.webp');
        $beta = $this->media('beta.webp');
        $gamma = $this->media('gamma.webp');
        $delta = $this->media('delta.webp');
        $epsilon = $this->media('epsilon.webp');
        foreach ([$alpha, $beta, $gamma, $delta, $epsilon] as $media) {
            $manager->persist($media);
        }

        $manager->persist($this->product('Pack alpha', 'pack-alpha', $biscuits, true)->setIsFeatured(true)->setPosition(2)->setMainImage($alpha));
        $manager->persist($this->product('Pack bêta', 'pack-beta', $lait, true)->setIsFeatured(false)->setPosition(1)->setMainImage($beta));
        $manager->persist($this->product('Pack gamma', 'pack-gamma', $lait, true)->setIsFeatured(true)->setPosition(3)->setMainImage($gamma));
        $manager->persist($this->product('Pack delta', 'pack-delta', $biscuits, true)->setIsFeatured(true)->setPosition(4)->setMainImage($delta));
        $manager->persist($this->product('Pack epsilon', 'pack-epsilon', $biscuits, false)->setIsFeatured(true)->setPosition(0)->setMainImage($epsilon));
        $manager->persist($this->product('Sans visuel', 'sans-visuel', $biscuits, true)->setIsFeatured(true)->setPosition(0));
        $manager->flush();

        $this->client->request('GET', '/nos-produits?categorie=lait');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $stageStart = strpos($content, 'page-intro__stage');
        $catalogStart = strpos($content, 'catalog-filters');
        self::assertNotFalse($stageStart);
        self::assertNotFalse($catalogStart);
        $stage = substr($content, (int) $stageStart, (int) $catalogStart - (int) $stageStart);

        self::assertStringContainsString('page-intro__stage--count-3', $stage);
        self::assertStringContainsString('aria-hidden="true"', $stage);
        self::assertStringContainsString('alt=""', $stage);
        self::assertStringContainsString('/uploads/media/alpha.webp', $stage);
        self::assertStringContainsString('/uploads/media/gamma.webp', $stage);
        self::assertStringContainsString('/uploads/media/delta.webp', $stage);
        self::assertStringNotContainsString('/uploads/media/beta.webp', $stage);
        self::assertStringNotContainsString('/uploads/media/epsilon.webp', $stage);
        self::assertStringNotContainsString('Pack epsilon', $content);
        self::assertLessThan(strpos($stage, 'gamma.webp'), strpos($stage, 'alpha.webp'));
        self::assertLessThan(strpos($stage, 'delta.webp'), strpos($stage, 'gamma.webp'));
    }

    private function manager(): EntityManagerInterface
    {
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }

    private function category(string $name, string $slug, CategoryIcon $icon, int $position): ProductCategory
    {
        return (new ProductCategory())
            ->setName($name)
            ->setSlug($slug)
            ->setIcon($icon)
            ->setAccentColor(CategoryAccent::Forest)
            ->setPosition($position)
            ->setIsPublished(true);
    }

    private function media(string $fileName): Media
    {
        return (new Media())
            ->setFileName($fileName)
            ->setOriginalName($fileName)
            ->setAlt('Visuel '.$fileName)
            ->setMimeType('image/webp')
            ->setSize(1200);
    }

    private function product(string $name, string $slug, ProductCategory $category, bool $published): Product
    {
        return (new Product())
            ->setName($name)
            ->setSlug($slug)
            ->setCategory($category)
            ->setShortDescription('Description de test.')
            ->setIsPublished($published)
            ->setPosition(1);
    }
}
