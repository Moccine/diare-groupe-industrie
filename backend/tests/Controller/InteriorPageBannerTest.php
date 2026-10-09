<?php

namespace App\Tests\Controller;

use App\Entity\Media;
use App\Entity\Page;
use App\Entity\Section;
use App\Enum\SectionType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InteriorPageBannerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite est requis pour les pages intérieures.');
        }

        $databaseUrl = 'sqlite:///'.dirname(__DIR__, 2).'/var/interior_page_phpunit.db';
        putenv('APP_ENV=test');
        putenv('DATABASE_URL='.$databaseUrl);
        $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $databaseUrl;

        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $manager = $this->manager();
        $params = $manager->getConnection()->getParams();
        if (($params['driver'] ?? '') !== 'pdo_sqlite') {
            self::markTestSkipped('Le test refuse de s’exécuter hors SQLite.');
        }

        $tool = new SchemaTool($manager);
        $metadata = $manager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testAboutPageUsesBannerImageAndPresentationVideo(): void
    {
        $manager = $this->manager();
        $banner = (new Media())
            ->setFileName('banniere-a-propos.webp')
            ->setOriginalName('a-propos.jpg')
            ->setAlt('Équipe sur une ligne de production agroalimentaire')
            ->setMimeType('image/webp')
            ->setSize(120000)
            ->setWidth(1920)
            ->setHeight(800);
        $manager->persist($banner);

        $about = (new Page())
            ->setTitle('À propos')
            ->setSlug('a-propos')
            ->setMetaDescription('Présentation de Diaré Groupe Industrie.')
            ->setIsPublished(true)
            ->setBannerImage($banner);
        $section = (new Section())
            ->setType(SectionType::TextImage)
            ->setTitle('Présentation de la société')
            ->setContent('Diaré Groupe Industrie fabrique des produits alimentaires.')
            ->setPosition(1)
            ->setIsVisible(true);
        $about->addSection($section);
        $manager->persist($about);
        $manager->flush();

        $this->client->request('GET', '/a-propos');

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('page-intro--photo', $html);
        self::assertStringContainsString('--page-banner-image: url(\'\\2F uploads\\2F media\\2F banniere\\2D a\\2D propos\\2E webp\')', $html);
        self::assertStringContainsString('class="presentation-video"', $html);
        self::assertMatchesRegularExpression('/<video[^>]*autoplay/', $html);
        self::assertMatchesRegularExpression('/<video[^>]*muted/', $html);
        self::assertMatchesRegularExpression('/<video[^>]*loop/', $html);
        self::assertMatchesRegularExpression('/<video[^>]*playsinline/', $html);
        self::assertMatchesRegularExpression('/<video[^>]*preload="metadata"/', $html);
        self::assertStringContainsString('presentation-diare-groupe-poster.webp', $html);
        self::assertStringContainsString('presentation-diare-groupe.mp4', $html);
        self::assertDoesNotMatchRegularExpression('/<video[^>]*\scontrols/', $html);
        self::assertStringContainsString('data-presentation-sound', $html);
        self::assertStringContainsString('aria-label="Activer le son"', $html);
        self::assertStringContainsString('aria-pressed="false"', $html);
        self::assertStringNotContainsString('/uploads/media/presentation', $html);
    }

    public function testKnownInteriorPageUsesTheCommittedBannerWhenNoneIsChosen(): void
    {
        $manager = $this->manager();
        $page = (new Page())
            ->setTitle('Distribution')
            ->setSlug('distribution')
            ->setIsPublished(true);
        $manager->persist($page);
        $manager->flush();

        $this->client->request('GET', '/distribution');

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('page-intro--distribution', $html);
        self::assertStringContainsString('page-intro--photo', $html);
        self::assertStringContainsString("--page-banner-image: url('\\2F banners\\2F distribution\\2E webp')", $html);
    }

    public function testInteriorPageWithoutBannerKeepsTheGraphicFallback(): void
    {
        $manager = $this->manager();
        $page = (new Page())
            ->setTitle('Mentions légales')
            ->setSlug('mentions-legales')
            ->setIsPublished(true);
        $manager->persist($page);
        $manager->flush();

        $this->client->request('GET', '/mentions-legales');

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('page-intro', $html);
        self::assertStringNotContainsString('page-intro--photo', $html);
        self::assertStringNotContainsString('--page-banner-image', $html);
    }

    private function manager(): EntityManagerInterface
    {
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }
}
