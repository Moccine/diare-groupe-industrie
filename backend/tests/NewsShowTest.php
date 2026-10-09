<?php

namespace App\Tests;

use App\Entity\Media;
use App\Entity\News;
use App\Entity\Page;
use App\Entity\SiteSettings;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NewsShowTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite est requis pour le test actualité.');
        }

        $databaseUrl = 'sqlite:///'.dirname(__DIR__).'/var/news_phpunit.db';
        putenv('APP_ENV=test');
        putenv('DATABASE_URL='.$databaseUrl);
        $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $databaseUrl;

        $this->client = static::createClient();
        $params = $this->manager()->getConnection()->getParams();
        if (($params['driver'] ?? '') !== 'pdo_sqlite') {
            self::markTestSkipped('Le test actualité refuse de s’exécuter hors SQLite.');
        }

        $manager = $this->manager();
        $tool = new SchemaTool($manager);
        $metadata = $manager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $manager->persist((new SiteSettings())->setCompanyName('Diaré Groupe Industrie'));
        $manager->persist((new Page())->setTitle('Actualités')->setSlug('actualites')->setIsPublished(true));
        $manager->persist((new Page())->setTitle('Nos activités')->setSlug('nos-activites')->setIsPublished(true));
        $manager->flush();
    }

    public function testNewsShowRendersEditorialLayoutWithoutPageBanner(): void
    {
        $manager = $this->manager();
        $cover = $this->media('actualite-cover.webp');
        $manager->persist($cover);
        $manager->persist($this->article(
            'Qualité des gammes alimentaires',
            'qualite-des-gammes-alimentaires',
            true,
            'Résumé éditorial visible.',
            "Premier paragraphe.\nDeuxième paragraphe.",
            $cover,
            new \DateTimeImmutable('2024-05-12 10:00:00'),
        ));
        $manager->persist($this->article(
            'Autre actualité',
            'autre-actualite',
            true,
            'Autre résumé.',
            'Contenu associé.',
            null,
            new \DateTimeImmutable('2024-04-01 10:00:00'),
        ));
        $manager->persist($this->article(
            'Actualité masquée',
            'actualite-masquee',
            false,
            null,
            'Ne doit pas apparaître.',
            null,
            new \DateTimeImmutable('2024-06-01 10:00:00'),
        ));
        $manager->flush();

        $this->client->request('GET', '/actualites/qualite-des-gammes-alimentaires');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('page-intro', $content);
        self::assertStringContainsString('news-article-page', $content);
        self::assertStringContainsString('news-article__hero', $content);
        self::assertStringContainsString('news-article__hero-copy', $content);
        self::assertStringNotContainsString('news-article__hero--text-only', $content);
        self::assertStringContainsString('Actualités du groupe', $content);
        self::assertStringContainsString('<h1>Qualité des gammes alimentaires</h1>', $content);
        self::assertSame(1, substr_count($content, '<h1'));
        self::assertStringContainsString('datetime="2024-05-12T10:00:00', $content);
        self::assertStringContainsString('Résumé éditorial visible.', $content);
        self::assertStringContainsString('news-article__hero-media', $content);
        self::assertSame(1, substr_count($content, 'src="/uploads/media/actualite-cover.webp"'));
        self::assertStringContainsString('fetchpriority="high"', $content);
        self::assertStringContainsString('Premier paragraphe.', $content);
        self::assertStringContainsString('news-article__prose', $content);
        self::assertStringNotContainsString('news-article__layout', $content);
        self::assertStringNotContainsString('Découvrir Diaré Groupe Industrie', $content);
        self::assertStringNotContainsString('← Retour aux actualités', $content);
        self::assertStringContainsString('À découvrir également', $content);
        self::assertStringContainsString('Autre actualité', $content);
        self::assertStringNotContainsString('Actualité masquée', $content);
    }

    public function testNewsShowHidesEmptyOptionalBlocks(): void
    {
        $manager = $this->manager();
        $manager->persist($this->article(
            'Article minimal',
            'article-minimal',
            true,
            null,
            'Texte seul.',
            null,
            new \DateTimeImmutable('2024-03-01 08:00:00'),
        ));
        $manager->flush();

        $this->client->request('GET', '/actualites/article-minimal');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('<h1>Article minimal</h1>', $content);
        self::assertStringContainsString('news-article__hero--text-only', $content);
        self::assertStringContainsString('Texte seul.', $content);
        self::assertStringNotContainsString('news-article__lead', $content);
        self::assertStringNotContainsString('news-article__hero-media', $content);
        self::assertStringNotContainsString('À découvrir également', $content);
        self::assertStringNotContainsString('media-placeholder', $content);
    }

    public function testUnpublishedNewsIsNotPublic(): void
    {
        $manager = $this->manager();
        $manager->persist($this->article(
            'Brouillon',
            'brouillon',
            false,
            null,
            'Privé.',
            null,
            new \DateTimeImmutable('2024-01-01 08:00:00'),
        ));
        $manager->flush();

        $this->client->request('GET', '/actualites/brouillon');

        self::assertResponseStatusCodeSame(404);
    }

    public function testNewsIndexKeepsListingBanner(): void
    {
        $manager = $this->manager();
        $manager->persist($this->article(
            'Visible listing',
            'visible-listing',
            true,
            'Résumé listing.',
            'Contenu.',
            null,
            new \DateTimeImmutable('2024-02-01 08:00:00'),
        ));
        $manager->flush();

        $this->client->request('GET', '/actualites');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('page-intro', $content);
        self::assertStringContainsString('Visible listing', $content);
    }

    private function manager(): EntityManagerInterface
    {
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
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

    private function article(
        string $title,
        string $slug,
        bool $published,
        ?string $excerpt,
        ?string $content,
        ?Media $image,
        \DateTimeImmutable $publishedAt,
    ): News {
        return (new News())
            ->setTitle($title)
            ->setSlug($slug)
            ->setExcerpt($excerpt)
            ->setContent($content)
            ->setImage($image)
            ->setIsPublished($published)
            ->setPublishedAt($publishedAt);
    }
}
