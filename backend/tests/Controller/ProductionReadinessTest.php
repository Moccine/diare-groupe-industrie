<?php

namespace App\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

final class ProductionReadinessTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite est requis pour les contrôles de production.');
        }

        self::ensureKernelShutdown();
        $this->bootClient();
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

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('DATABASE_URL');
        putenv('DEFAULT_URI');
        putenv('GOOGLE_ANALYTICS_MEASUREMENT_ID');
        putenv('APP_INDEXABLE');
        unset(
            $_ENV['DATABASE_URL'],
            $_SERVER['DATABASE_URL'],
            $_ENV['DEFAULT_URI'],
            $_SERVER['DEFAULT_URI'],
            $_ENV['GOOGLE_ANALYTICS_MEASUREMENT_ID'],
            $_SERVER['GOOGLE_ANALYTICS_MEASUREMENT_ID'],
            $_ENV['APP_INDEXABLE'],
            $_SERVER['APP_INDEXABLE'],
        );
    }

    public function testPublicRobotsSitemapAndAdminEntry(): void
    {
        $this->client->request('GET', '/robots.txt');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Disallow: /administration', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('Sitemap:', (string) $this->client->getResponse()->getContent());
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('X-Frame-Options', 'SAMEORIGIN');
        self::assertResponseHeaderSame('Referrer-Policy', 'strict-origin-when-cross-origin');

        $this->client->request('GET', '/sitemap.xml');
        self::assertResponseIsSuccessful();
        $sitemap = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('<urlset', $sitemap);
        self::assertStringContainsString('http://localhost/', $sitemap);
        self::assertStringContainsString('/contact', $sitemap);

        $this->client->request('GET', '/administration');
        self::assertResponseRedirects('/administration/connexion');

        $this->client->request('GET', '/administration/connexion');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('_csrf_token', (string) $this->client->getResponse()->getContent());
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
    }

    public function testAnalyticsStaysOffWithoutMeasurementId(): void
    {
        $this->client->request('GET', '/contact');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('googletagmanager.com', $content);
        self::assertStringNotContainsString('data-analytics-banner', $content);
        self::assertStringNotContainsString('data-analytics-consent-reset', $content);
        self::assertStringNotContainsString('name="robots"', $content);
        self::assertNull($this->client->getResponse()->headers->get('Content-Security-Policy'));
    }

    public function testAnalyticsBannerDoesNotLoadTheScriptBeforeConsent(): void
    {
        $client = $this->rebootWith([
            'GOOGLE_ANALYTICS_MEASUREMENT_ID' => 'G-TEST1234',
        ]);

        $client->request('GET', '/contact');
        $content = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('data-analytics-banner', $content);
        self::assertStringContainsString('Accepter', $content);
        self::assertStringContainsString('Refuser', $content);
        self::assertStringNotContainsString('googletagmanager.com/gtag/js', $content);
        self::assertStringNotContainsString('G-TEST1234', $content);
    }

    public function testAnalyticsScriptLoadsOnlyAfterAcceptedConsent(): void
    {
        $client = $this->rebootWith([
            'GOOGLE_ANALYTICS_MEASUREMENT_ID' => 'G-TEST1234',
        ]);
        $client->getCookieJar()->set(new Cookie('analytics_consent', 'accepted', (string) strtotime('+1 day'), '/', 'localhost'));

        $client->request('GET', '/contact');
        $content = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('googletagmanager.com/gtag/js?id=G-TEST1234', $content);
        self::assertStringContainsString("gtag('config', 'G\\u002DTEST1234')", $content);
        self::assertStringNotContainsString('data-analytics-banner', $content);
    }

    public function testRefusedAnalyticsCanBeChangedFromTheFooter(): void
    {
        $client = $this->rebootWith([
            'GOOGLE_ANALYTICS_MEASUREMENT_ID' => 'G-TEST1234',
        ]);
        $client->getCookieJar()->set(new Cookie('analytics_consent', 'refused', (string) strtotime('+1 day'), '/', 'localhost'));

        $client->request('GET', '/contact');
        $content = (string) $client->getResponse()->getContent();

        self::assertStringNotContainsString('googletagmanager.com', $content);
        self::assertStringNotContainsString('data-analytics-banner', $content);
        self::assertStringContainsString('data-analytics-consent-reset', $content);
    }

    public function testPreproductionRobotsDisallowIndexing(): void
    {
        $client = $this->rebootWith([
            'APP_INDEXABLE' => '0',
        ]);

        $client->request('GET', '/robots.txt');
        $robots = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Disallow: /', $robots);
        self::assertStringNotContainsString('Sitemap:', $robots);

        $client->request('GET', '/contact');
        self::assertStringContainsString('noindex, nofollow', (string) $client->getResponse()->getContent());
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
    }

    public function testWwwHostRedirectsToTheCanonicalHost(): void
    {
        $client = $this->rebootWith([
            'DEFAULT_URI' => 'https://diaregroupe.com',
        ]);

        $client->request('GET', '/contact?sujet=distribution', [], [], ['HTTP_HOST' => 'www.diaregroupe.com']);

        self::assertResponseRedirects('https://diaregroupe.com/contact?sujet=distribution', 301);
    }

    public function testUnknownHostIsNotRedirected(): void
    {
        $client = $this->rebootWith([
            'DEFAULT_URI' => 'https://diaregroupe.com',
        ]);

        $client->request('GET', '/contact', [], [], ['HTTP_HOST' => 'evil.example']);

        self::assertResponseIsSuccessful();
    }

    public function testMessengerTransportsAreRegistered(): void
    {
        self::assertTrue(static::getContainer()->has('messenger.transport.async'));
        self::assertTrue(static::getContainer()->has('messenger.transport.failed'));
    }

    private function bootClient(): void
    {
        $databaseUrl = 'sqlite:///'.dirname(__DIR__, 2).'/var/production_readiness.db';
        putenv('APP_ENV=test');
        putenv('DATABASE_URL='.$databaseUrl);
        putenv('GOOGLE_ANALYTICS_MEASUREMENT_ID=');
        putenv('APP_INDEXABLE=1');
        putenv('DEFAULT_URI=http://localhost');
        $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $databaseUrl;
        $_SERVER['GOOGLE_ANALYTICS_MEASUREMENT_ID'] = $_ENV['GOOGLE_ANALYTICS_MEASUREMENT_ID'] = '';
        $_SERVER['APP_INDEXABLE'] = $_ENV['APP_INDEXABLE'] = '1';
        $_SERVER['DEFAULT_URI'] = $_ENV['DEFAULT_URI'] = 'http://localhost';

        $this->client = static::createClient();
    }

    /** @param array<string, string> $variables */
    private function rebootWith(array $variables): KernelBrowser
    {
        self::ensureKernelShutdown();
        foreach ($variables as $name => $value) {
            putenv($name.'='.$value);
            $_SERVER[$name] = $_ENV[$name] = $value;
        }

        return static::createClient();
    }

    private function manager(): EntityManagerInterface
    {
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }
}
