<?php

namespace App\Service;

final class RecaptchaDecision
{
    private function __construct(
        private readonly bool $accepted,
    ) {
    }

    public static function accepted(): self
    {
        return new self(true);
    }

    public static function rejected(): self
    {
        return new self(false);
    }

    public function isAccepted(): bool
    {
        return $this->accepted;
    }
}
