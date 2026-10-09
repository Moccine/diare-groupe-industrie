<?php

namespace App\Tests\Service;

use App\Entity\Media;
use App\Entity\SiteSettings;
use App\Service\EmailBranding;
use PHPUnit\Framework\TestCase;

final class EmailBrandingTest extends TestCase
{
    public function testPublicHttpsOriginExposesTheOfficialLogoAndDropsLocalUrls(): void
    {
        $settings = new SiteSettings();
        $branding = new EmailBranding('https://diare.example/accueil');

        $logo = $branding->logo($settings);

        self::assertNotNull($logo);
        self::assertSame('https://diare.example/brand/logo.png', $logo['url']);
        self::assertSame('https://diare.example', $branding->siteUrl());
        self::assertSame(58, $logo['width']);
        self::assertSame(64, $logo['height']);
    }

    public function testConfiguredLogoKeepsASafePublicPathAndItsOwnSize(): void
    {
        $logo = (new Media())
            ->setFileName('logo-officiel.webp')
            ->setWidth(200)
            ->setHeight(80);
        $settings = (new SiteSettings())->setLogo($logo);

        $resolved = (new EmailBranding('https://diare.example'))->logo($settings);

        self::assertNotNull($resolved);
        self::assertSame('https://diare.example/uploads/media/logo-officiel.webp', $resolved['url']);
        self::assertSame(64, $resolved['width']);
        self::assertSame(26, $resolved['height']);
    }

    public function testUnsafeLogoNamesFallBackToTheBrandFile(): void
    {
        $settings = (new SiteSettings())->setLogo((new Media())->setFileName('../secret.png'));

        $logo = (new EmailBranding('https://diare.example'))->logo($settings);

        self::assertNotNull($logo);
        self::assertSame('https://diare.example/brand/logo.png', $logo['url']);
    }

    public function testLocalAndNonHttpsOriginsDoNotProduceALogo(): void
    {
        $settings = new SiteSettings();
        $rejected = [
            'http://localhost:8086',
            'http://diare.example',
            'https://localhost',
            'https://127.0.0.1',
            'https://10.0.0.8',
            'https://192.168.1.20',
            'https://mail.diaregroupe.local',
            'https://user:secret@diare.example',
            '',
        ];

        foreach ($rejected as $uri) {
            $branding = new EmailBranding($uri);
            self::assertNull($branding->siteUrl(), $uri);
            self::assertNull($branding->logo($settings), $uri);
        }
    }

    public function testColorsStayInsideAHexValue(): void
    {
        $branding = new EmailBranding('https://diare.example');

        self::assertSame('#112233', $branding->color('#112233', '#185424'));
        self::assertSame('#185424', $branding->color('#fff;background:url(https://evil.example)', '#185424'));
        self::assertSame('#185424', $branding->color('red', '#185424'));
        self::assertSame('#AABBCC', $branding->color('  #AABBCC  ', '#185424'));
    }
}
