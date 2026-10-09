<?php

namespace App\Tests;

use App\Entity\JobApplication;
use App\Enum\JobApplicationStatus;
use PHPUnit\Framework\TestCase;

final class JobApplicationStatusTest extends TestCase
{
    public function testInitialStatusIsNewAndFinalDecisionSetsProcessedAt(): void
    {
        $application = new JobApplication();

        self::assertSame(JobApplicationStatus::New, $application->getStatus());
        self::assertSame('Nouvelle', $application->getStatusLabel());
        self::assertNull($application->getProcessedAt());

        $application->setStatus(JobApplicationStatus::InReview);
        self::assertNull($application->getProcessedAt());

        $before = new \DateTimeImmutable('-1 second');
        $application->setStatus(JobApplicationStatus::Rejected);
        self::assertNotNull($application->getProcessedAt());
        self::assertGreaterThanOrEqual($before, $application->getProcessedAt());

        $processedAt = $application->getProcessedAt();
        $application->setStatus(JobApplicationStatus::Hired);
        self::assertSame($processedAt, $application->getProcessedAt());
    }

    public function testMotivationKeepsLineBreaksAndDropsMarkup(): void
    {
        $application = (new JobApplication())
            ->setMotivation("Ligne 1\n<script>alert(1)</script>\nLigne 2 avec assez de texte utile.");

        self::assertStringContainsString("Ligne 1\n", $application->getMotivation());
        self::assertStringContainsString('Ligne 2', $application->getMotivation());
        self::assertStringNotContainsString('<script>', $application->getMotivation());
    }

    public function testNamesCollapseSpaces(): void
    {
        $application = (new JobApplication())
            ->setFirstName("  Amina   \n Diallo ")
            ->setLastName('  Camara   ');

        self::assertSame('Amina Diallo', $application->getFirstName());
        self::assertSame('Camara', $application->getLastName());
    }
}
