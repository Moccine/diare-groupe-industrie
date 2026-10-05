<?php

namespace App\Enum;

enum CategoryAccent: string
{
    case Forest = 'forest';
    case Deep = 'deep';
    case Lime = 'lime';
    case Biscuit = 'biscuit';
    case Rose = 'rose';

    public function label(): string
    {
        return match ($this) {
            self::Forest => 'Vert',
            self::Deep => 'Vert sombre',
            self::Lime => 'Lime',
            self::Biscuit => 'Jaune biscuit',
            self::Rose => 'Rose packaging',
        };
    }

    public function cssColor(): string
    {
        return match ($this) {
            self::Forest => '#185424',
            self::Deep => '#0E3A18',
            self::Lime => '#90B43C',
            self::Biscuit => '#E2A423',
            self::Rose => '#B55260',
        };
    }

    /** Encre lisible posée sur la pastille, jamais une teinte claire en petit texte. */
    public function ink(): string
    {
        return match ($this) {
            self::Lime => '#0E3A18',
            self::Biscuit => '#3A2A0A',
            self::Forest, self::Deep, self::Rose => '#ffffff',
        };
    }
}
