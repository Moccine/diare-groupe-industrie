<?php

namespace App\Tests\Service;

use App\Entity\JobOffer;
use App\Entity\SiteSettings;
use App\Service\JobApplicationRecipientResolver;
use PHPUnit\Framework\TestCase;

final class JobApplicationRecipientResolverTest extends TestCase
{
    public function testOfferEmailWinsOverConfiguredAndPublicAddresses(): void
    {
        $offer = (new JobOffer())->setApplicationEmail('rh-poste@example.com');
        $settings = (new SiteSettings())->setEmail('contact@example.com');

        self::assertSame('rh-poste@example.com', $this->resolver('rh@example.com')->resolve($offer, $settings));
    }

    public function testConfiguredAddressIsUsedWhenTheOfferHasNone(): void
    {
        $settings = (new SiteSettings())->setEmail('contact@example.com');

        self::assertSame('rh@example.com', $this->resolver('rh@example.com')->resolve(null, $settings));
    }

    public function testPublicSiteEmailIsTheLastConfiguredFallback(): void
    {
        $settings = (new SiteSettings())->setEmail('contact@example.com');

        self::assertSame('contact@example.com', $this->resolver('')->resolve(new JobOffer(), $settings));
    }

    public function testMissingAddressesDoNotInventARecipient(): void
    {
        self::assertNull($this->resolver('pas-un-email')->resolve(new JobOffer(), new SiteSettings()));
    }

    private function resolver(string $configured): JobApplicationRecipientResolver
    {
        return new JobApplicationRecipientResolver($configured);
    }
}
