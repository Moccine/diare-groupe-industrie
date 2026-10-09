<?php

namespace App\Service;

use App\Entity\JobApplication;

final class JobApplicationSubmissionLock
{
    public function exclusive(JobApplication $application, callable $callback): mixed
    {
        $handle = $this->open($application);

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Verrou de candidature indisponible.');
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return resource */
    private function open(JobApplication $application)
    {
        $directory = sys_get_temp_dir().'/dgi-job-application-locks';
        if (!is_dir($directory) && !@mkdir($directory, 01777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Verrou de candidature indisponible.');
        }
        @chmod($directory, 01777);

        $path = $directory.DIRECTORY_SEPARATOR.$this->name($application).'.lock';
        $handle = fopen($path, 'c');
        if ($handle === false) {
            throw new \RuntimeException('Verrou de candidature indisponible.');
        }
        @chmod($path, 0666);

        return $handle;
    }

    private function name(JobApplication $application): string
    {
        $offerId = $application->getJobOffer()?->getId();
        $role = $application->isSpontaneous()
            ? mb_strtolower(trim((string) $application->getDesiredRole()))
            : '';

        return hash('sha256', implode("\0", [
            mb_strtolower(trim($application->getEmail())),
            $application->isSpontaneous() ? '1' : '0',
            $offerId === null ? '' : (string) $offerId,
            $role,
        ]));
    }
}
