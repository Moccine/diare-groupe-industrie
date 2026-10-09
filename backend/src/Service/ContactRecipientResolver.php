<?php

namespace App\Service;

use App\Entity\SiteSettings;

final class ContactRecipientResolver
{
    public function __construct(
        private readonly string $contactNotifyEmail,
    ) {
    }

    public function staff(?SiteSettings $settings): ?string
    {
        $configured = $this->validEmail($this->contactNotifyEmail);
        if ($configured !== null) {
            return $configured;
        }

        if ($settings?->hasPublicEmail()) {
            return $settings->getEmail();
        }

        return null;
    }

    public function replyTo(?SiteSettings $settings): ?string
    {
        if ($settings?->hasPublicEmail()) {
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
