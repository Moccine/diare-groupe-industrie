<?php

namespace App\Enum;

enum SectionTheme: string
{
    case Default = 'default';
    case Dark = 'dark';
    case Light = 'light';
    case ImageLeft = 'image_left';

    public function label(): string
    {
        return match ($this) {
            self::Default => 'Par défaut',
            self::Dark => 'Fond sombre',
            self::Light => 'Fond clair',
            self::ImageLeft => 'Image à gauche',
        };
    }
}
