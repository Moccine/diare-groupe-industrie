<?php

namespace App\Enum;

enum JobApplicationStatus: string
{
    case New = 'new';
    case InReview = 'in_review';
    case Shortlisted = 'shortlisted';
    case Interview = 'interview';
    case Hired = 'hired';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nouvelle',
            self::InReview => 'À étudier',
            self::Shortlisted => 'Présélectionnée',
            self::Interview => 'Entretien',
            self::Hired => 'Retenue',
            self::Rejected => 'Refusée',
            self::Withdrawn => 'Retirée',
        };
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::Hired, self::Rejected, self::Withdrawn => true,
            default => false,
        };
    }
}
