<?php

namespace App\Enum;

enum HeroContentPosition: string
{
    case Start = 'start';
    case Center = 'center';
    case End = 'end';

    public function label(): string
    {
        return match ($this) {
            self::Start => 'Gauche',
            self::Center => 'Centre',
            self::End => 'Droite',
        };
    }
}
