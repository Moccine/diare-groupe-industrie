<?php

namespace App\Enum;

enum HeroOverlay: string
{
    case Soft = 'soft';
    case Medium = 'medium';
    case Strong = 'strong';

    public function label(): string
    {
        return match ($this) {
            self::Soft => 'Léger',
            self::Medium => 'Moyen',
            self::Strong => 'Soutenu',
        };
    }
}
