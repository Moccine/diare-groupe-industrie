<?php

namespace App\Tests;

use App\Admin\SectionFormVisibility;
use App\Enum\SectionType;
use PHPUnit\Framework\TestCase;

final class SectionFormVisibilityTest extends TestCase
{
    public function testEveryTypeHasAGuide(): void
    {
        foreach (SectionType::cases() as $type) {
            self::assertNotSame('', trim($type->guide()));
            self::assertNotSame('', trim($type->label()));
        }
    }

    public function testVisibilityOnlyUsesKnownTypes(): void
    {
        self::assertTrue(SectionFormVisibility::coversKnownTypes());
    }

    public function testHeroUsesItsOwnImageButNotTheItemList(): void
    {
        self::assertStringContainsString('dgi-when-hero', SectionFormVisibility::cssClass('image'));
        self::assertStringNotContainsString('dgi-when-hero', SectionFormVisibility::cssClass('items'));
        self::assertStringNotContainsString('dgi-when-partners', SectionFormVisibility::cssClass('items'));
        self::assertSame('', SectionFormVisibility::cssClass('title'));
    }

    public function testBannerLabelIsUnderstandable(): void
    {
        self::assertSame('Bannière', SectionType::Hero->label());
    }
}
