<?php

namespace App\Service;

use App\Entity\JobApplication;
use App\Repository\JobApplicationRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class JobApplicationSubmitter
{
    public function __construct(
        private readonly JobApplicationRepository $applications,
        private readonly JobApplicationRecorder $recorder,
        private readonly JobApplicationNotifier $notifier,
        private readonly PublicContent $publicContent,
        private readonly JobApplicationSubmissionLock $locks,
    ) {
    }

    public function submit(JobApplication $application, UploadedFile $cv): string
    {
        $created = $this->locks->exclusive($application, function () use ($application, $cv): bool {
            $duplicate = $this->applications->findRecentDuplicate(
                $application->getEmail(),
                $application->getJobOffer()?->getId(),
                $application->isSpontaneous(),
                $application->getDesiredRole(),
                new \DateTimeImmutable('-60 seconds'),
            );
            if ($duplicate !== null) {
                return false;
            }

            $this->recorder->record($application, $cv);

            return true;
        });

        if (!$created) {
            return 'duplicate';
        }

        $this->notifier->notify($application, $this->publicContent->settings());

        return 'created';
    }
}
