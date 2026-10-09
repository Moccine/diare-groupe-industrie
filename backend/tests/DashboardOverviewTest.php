<?php

namespace App\Tests;

use App\Entity\HeroSlide;
use App\Entity\JobOffer;
use App\Entity\News;
use App\Entity\Page;
use App\Entity\Partner;
use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\Section;
use App\Entity\SiteSettings;
use App\Enum\CategoryAccent;
use App\Enum\CategoryIcon;
use App\Enum\SectionType;
use App\Service\DashboardOverview;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DashboardOverviewTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite est requis.');
        }

        $databaseUrl = 'sqlite:///'.dirname(__DIR__).'/var/dashboard_phpunit.db';
        putenv('APP_ENV=test');
        putenv('DATABASE_URL='.$databaseUrl);
        putenv('CONTACT_NOTIFY_EMAIL=');
        $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $databaseUrl;
        $_SERVER['CONTACT_NOTIFY_EMAIL'] = $_ENV['CONTACT_NOTIFY_EMAIL'] = '';

        $this->client = static::createClient();
        $params = $this->manager()->getConnection()->getParams();
        if (($params['driver'] ?? '') !== 'pdo_sqlite') {
            self::markTestSkipped('Le test refuse de s’exécuter hors SQLite.');
        }

        $manager = $this->manager();
        $tool = new SchemaTool($manager);
        $metadata = $manager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testGapsAreActionable(): void
    {
        $manager = $this->manager();
        $manager->persist(new SiteSettings());

        $category = (new ProductCategory())
            ->setName('Lait')
            ->setSlug('lait')
            ->setIcon(CategoryIcon::Generic)
            ->setAccentColor(CategoryAccent::Forest);
        $manager->persist($category);

        $manager->persist((new Product())->setName('Lait frais')->setSlug('lait-frais'));

        $manager->persist((new Page())->setTitle('Page vide')->setSlug('page-vide'));

        $page = (new Page())->setTitle('À propos')->setSlug('a-propos');
        $hero = (new Section())->setPage($page)->setType(SectionType::Hero)->setTitle('Accueil')->setPosition(1);
        $slide = (new HeroSlide())->setSection($hero)->setTitle('Sans image')->setPosition(1);
        $manager->persist($page);
        $manager->persist($hero);
        $manager->persist($slide);

        $manager->persist((new News())->setTitle('Inauguration')->setSlug('inauguration')->setIsPublished(true));
        $manager->persist((new Partner())->setName('Partenaire sans logo'));
        $manager->persist((new JobOffer())->setTitle('Opérateur')->setSlug('operateur')->setIsPublished(true));
        $manager->persist(
            (new JobOffer())
                ->setTitle('Ancienne offre')
                ->setSlug('ancienne-offre')
                ->setIsPublished(true)
                ->setExpiresAt(new \DateTimeImmutable('-1 day'))
        );
        $manager->flush();

        $this->client->request('GET', '/');
        $overview = static::getContainer()->get(DashboardOverview::class);
        self::assertInstanceOf(DashboardOverview::class, $overview);
        $data = $overview->build();

        self::assertSame(1, $data['publishedProducts']);
        self::assertFalse($data['emailConfigured']);
        self::assertFalse($data['mapConfigured']);
        self::assertFalse($data['logoConfigured']);
        self::assertNotSame('', $data['settingsUrl']);

        $labels = array_column($data['gaps'], 'label');
        self::assertNotEmpty($labels);
        $text = implode("\n", $labels);
        self::assertStringContainsString('logo du site', $text);
        self::assertStringContainsString('email de contact', $text);
        self::assertStringContainsString('alerte des messages de contact', $text);
        self::assertStringContainsString('carte du site', $text);
        self::assertStringContainsString('image principale', $text);
        self::assertStringContainsString('pas de catégorie', $text);
        self::assertStringContainsString('actualité publiée', $text);
        self::assertStringContainsString('bannière active', $text);
        self::assertStringContainsString('lien de candidature', $text);
        self::assertStringContainsString('expiration', $text);
        self::assertStringContainsString('partenaire publié', $text);
        self::assertStringContainsString('catégorie publiée', $text);
        self::assertStringContainsString('aucun contenu visible', $text);

        foreach ($data['gaps'] as $gap) {
            self::assertNotSame('', $gap['url']);
            self::assertContains($gap['action'], ['Corriger', 'Voir']);
            self::assertContains($gap['type'], ['warning', 'info']);
        }
    }

    public function testContactAlertWarningIsHiddenWhenNotifyEmailIsConfigured(): void
    {
        self::ensureKernelShutdown();
        putenv('CONTACT_NOTIFY_EMAIL=alertes@example.test');
        $_SERVER['CONTACT_NOTIFY_EMAIL'] = $_ENV['CONTACT_NOTIFY_EMAIL'] = 'alertes@example.test';
        $this->client = static::createClient();
        $this->manager()->persist(new SiteSettings());
        $this->manager()->flush();

        $overview = static::getContainer()->get(DashboardOverview::class);
        self::assertInstanceOf(DashboardOverview::class, $overview);
        $text = implode("\n", array_column($overview->build()['gaps'], 'label'));

        self::assertStringNotContainsString('alerte des messages de contact', $text);
        self::assertStringContainsString('email de contact', $text);
    }

    protected function tearDown(): void
    {
        putenv('CONTACT_NOTIFY_EMAIL');
        unset($_ENV['CONTACT_NOTIFY_EMAIL'], $_SERVER['CONTACT_NOTIFY_EMAIL']);

        parent::tearDown();
    }

    private function manager(): EntityManagerInterface
    {
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }
}
