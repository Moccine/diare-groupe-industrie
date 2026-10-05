<?php

namespace App\Enum;

enum ContractType: string
{
    case Cdi = 'cdi';
    case Cdd = 'cdd';
    case Internship = 'stage';
    case Apprenticeship = 'alternance';
    case Freelance = 'freelance';
    case Other = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Cdi => 'CDI',
            self::Cdd => 'CDD',
            self::Internship => 'Stage',
            self::Apprenticeship => 'Alternance',
            self::Freelance => 'Freelance',
            self::Other => 'Autre',
        };
    }
}
