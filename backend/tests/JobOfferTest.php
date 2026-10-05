<?php

namespace App\Tests;

use App\Entity\JobOffer;
use App\Enum\ContractType;
use PHPUnit\Framework\TestCase;

final class JobOfferTest extends TestCase
{
    public function testPublishedOfferIsOpen(): void
    {
        self::assertTrue($this->offer()->isOpenAt(new \DateTimeImmutable()));
    }

    public function testExpiredOfferIsClosed(): void
    {
        $offer = $this->offer()->setExpiresAt(new \DateTimeImmutable('-1 hour'));

        self::assertFalse($offer->isOpenAt(new \DateTimeImmutable()));
    }

    public function testOfferClosesAtTheExactExpiry(): void
    {
        $now = new \DateTimeImmutable('2026-10-04 12:00:00');
        $offer = $this->offer()->setExpiresAt($now);

        self::assertFalse($offer->isOpenAt($now));
    }

    public function testFutureOfferIsClosed(): void
    {
        $offer = $this->offer()->setPublishedAt(new \DateTimeImmutable('+2 days'));

        self::assertFalse($offer->isOpenAt(new \DateTimeImmutable()));
    }

    public function testUnpublishedOfferIsClosed(): void
    {
        $offer = $this->offer()->setIsPublished(false);

        self::assertFalse($offer->isOpenAt(new \DateTimeImmutable()));
    }

    public function testApplicationUrlIsPreferred(): void
    {
        $offer = $this->offer()
            ->setApplicationUrl('https://example.com/jobs')
            ->setApplicationEmail('jobs@example.com');

        self::assertSame('https://example.com/jobs', $offer->getApplicationHref());
        self::assertTrue($offer->isExternalApplication());
    }

    public function testMailtoWhenNoUrl(): void
    {
        $offer = $this->offer()->setApplicationEmail('jobs@example.com');

        self::assertStringStartsWith('mailto:jobs@example.com?subject=', (string) $offer->getApplicationHref());
        self::assertFalse($offer->isExternalApplication());
    }

    public function testNoApplicationTargetWithoutContact(): void
    {
        self::assertNull($this->offer()->getApplicationHref());
    }

    public function testContractLabelsStayAdministrative(): void
    {
        self::assertSame('CDI', ContractType::Cdi->label());
        self::assertSame('CDD', ContractType::Cdd->label());
        self::assertSame('Stage', ContractType::Internship->label());
        self::assertSame('Alternance', ContractType::Apprenticeship->label());
        self::assertSame('Freelance', ContractType::Freelance->label());
        self::assertSame('Autre', ContractType::Other->label());
    }

    private function offer(): JobOffer
    {
        return (new JobOffer())
            ->setTitle('Responsable qualité')
            ->setSlug('responsable-qualite')
            ->setContractType(ContractType::Cdi)
            ->setIsPublished(true)
            ->setPublishedAt(new \DateTimeImmutable('-1 day'));
    }
}
