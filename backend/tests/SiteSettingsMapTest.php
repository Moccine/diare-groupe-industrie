<?php

namespace App\Tests;

use App\Entity\SiteSettings;
use App\Enum\CategoryAccent;
use PHPUnit\Framework\TestCase;

final class SiteSettingsMapTest extends TestCase
{
    public function testMapStaysHiddenWithoutCoordinates(): void
    {
        $settings = new SiteSettings();

        self::assertFalse($settings->hasMap());
        self::assertNull($settings->getMapEmbedUrl());
        self::assertNull($settings->getMapDirectionsUrl());
    }

    public function testPartialCoordinatesDoNotShowAMap(): void
    {
        $settings = (new SiteSettings())->setMapLatitude('10.5');

        self::assertFalse($settings->hasMap());
        self::assertNull($settings->getMapEmbedUrl());
    }

    public function testEmbedUsesOpenStreetMapWhenCoordinatesExist(): void
    {
        $settings = (new SiteSettings())
            ->setMapLatitude('10')
            ->setMapLongitude('-10')
            ->setMapZoom(14);

        $url = $settings->getMapEmbedUrl();

        self::assertNotNull($url);
        self::assertStringStartsWith('https://www.openstreetmap.org/export/embed.html?bbox=', $url);
        self::assertStringContainsString('marker=', $url);
        self::assertStringContainsString('10.000000', $url);
        self::assertStringContainsString('-10.000000', $url);
        self::assertStringContainsString('openstreetmap.org/directions?to=', (string) $settings->getMapDirectionsUrl());
    }

    public function testMissingZoomFallsBackToFifteen(): void
    {
        $settings = (new SiteSettings())
            ->setMapLatitude('1')
            ->setMapLongitude('2')
            ->setMapZoom(null);

        self::assertSame(15, $settings->resolvedMapZoom());
        self::assertNotNull($settings->getMapEmbedUrl());
    }

    public function testLimeAccentKeepsDarkInk(): void
    {
        self::assertSame('#90B43C', CategoryAccent::Lime->cssColor());
        self::assertSame('#0E3A18', CategoryAccent::Lime->ink());
        self::assertNotSame('#ffffff', CategoryAccent::Lime->ink());
        self::assertSame('#E2A423', CategoryAccent::Biscuit->cssColor());
        self::assertSame('#3A2A0A', CategoryAccent::Biscuit->ink());
        self::assertSame('#ffffff', CategoryAccent::Rose->ink());
    }
}
