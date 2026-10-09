<?php

namespace App\Service;

use App\Entity\JobApplication;
use App\Entity\JobOffer;
use App\Entity\SiteSettings;

final class JobApplicationRecipientResolver
{
    public function __construct(
        private readonly string $jobApplicationNotifyEmail,
    ) {
    }

    public function resolve(?JobOffer $offer, SiteSettings $settings): ?string
    {
        $dedicated = $this->validEmail($offer?->getApplicationEmail());
        if ($dedicated !== null) {
            return $dedicated;
        }

        $configured = $this->validEmail($this->jobApplicationNotifyEmail);
        if ($configured !== null) {
            return $configured;
        }

        if ($settings->hasPublicEmail()) {
            return $settings->getEmail();
        }

        return null;
    }

    private function validEmail(?string $email): ?string
    {
        $email = trim((string) $email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $email;
    }
}
