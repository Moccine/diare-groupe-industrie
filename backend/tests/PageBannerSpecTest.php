<?php

namespace App\Tests;

use App\Entity\Media;
use App\Media\PageBannerSpec;
use PHPUnit\Framework\TestCase;

final class PageBannerSpecTest extends TestCase
{
    public function testNullMediaIsAllowed(): void
    {
        self::assertNull(PageBannerSpec::violation(null));
    }

    public function testRecommendedLandscapeIsAccepted(): void
    {
        $media = (new Media())->setWidth(1920)->setHeight(640);

        self::assertNull(PageBannerSpec::violation($media));
    }

    public function testWidePhotoIsAccepted(): void
    {
        $media = (new Media())->setWidth(1920)->setHeight(1080);

        self::assertNull(PageBannerSpec::violation($media));
    }

    public function testUndersizedImageIsRejected(): void
    {
        $media = (new Media())->setWidth(800)->setHeight(500);

        self::assertStringContainsString('trop petite', (string) PageBannerSpec::violation($media));
    }

    public function testPortraitImageIsRejected(): void
    {
        $media = (new Media())->setWidth(1600)->setHeight(2000);

        self::assertStringContainsString('horizontale', (string) PageBannerSpec::violation($media));
    }

    public function testUnknownDimensionsAreRejected(): void
    {
        self::assertStringContainsString('pas connues', (string) PageBannerSpec::violation(new Media()));
    }
}
