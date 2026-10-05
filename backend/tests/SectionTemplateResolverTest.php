<?php

namespace App\Tests;

use App\Enum\SectionType;
use App\Service\SectionTemplateResolver;
use PHPUnit\Framework\TestCase;

final class SectionTemplateResolverTest extends TestCase
{
    public function testKnownTypeResolvesWhenTemplateExists(): void
    {
        $resolver = new SectionTemplateResolver(dirname(__DIR__).'/templates');

        self::assertSame('sections/_hero.html.twig', $resolver->resolve(SectionType::Hero));
        self::assertSame('sections/_products.html.twig', $resolver->resolve(SectionType::Products));
    }

    public function testEverySectionTypeHasATemplate(): void
    {
        $resolver = new SectionTemplateResolver(dirname(__DIR__).'/templates');

        foreach (SectionType::cases() as $type) {
            self::assertNotNull($resolver->resolve($type), $type->value);
        }
    }
}
