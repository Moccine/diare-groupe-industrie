<?php

namespace App\Enum;

enum CategoryIcon: string
{
    case Milk = 'milk';
    case Biscuit = 'biscuit';
    case Sugar = 'sugar';
    case Generic = 'generic';

    public function label(): string
    {
        return match ($this) {
            self::Milk => 'Lait',
            self::Biscuit => 'Biscuits',
            self::Sugar => 'Sucre',
            self::Generic => 'Générique',
        };
    }
}
