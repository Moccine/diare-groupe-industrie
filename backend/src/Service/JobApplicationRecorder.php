<?php

namespace App\Service;

use App\Entity\JobApplication;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class JobApplicationRecorder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JobApplicationCvStorage $storage,
        private readonly LoggerInterface $logger,
        private readonly string $jobApplicationRetentionMonths,
    ) {
    }

    public function record(JobApplication $application, UploadedFile $cv): void
    {
        $stored = $this->storage->store($cv);
        $application->attachCv(
            $stored->storedFilename,
            $stored->originalFilename,
            $stored->mimeType,
            $stored->size,
        );
        $this->applyRetention($application);

        try {
            $this->entityManager->persist($application);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $this->storage->delete($stored->storedFilename);
            $this->logger->error('Enregistrement de la candidature impossible.', [
                'error' => $exception::class,
            ]);

            throw $exception;
        }
    }

    private function applyRetention(JobApplication $application): void
    {
        $months = trim($this->jobApplicationRetentionMonths);
        if ($months === '' || !preg_match('/^[1-9][0-9]{0,2}$/', $months)) {
            return;
        }

        $application->setRetentionUntil(new \DateTimeImmutable('+'.$months.' months'));
    }
}
