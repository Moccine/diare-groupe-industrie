<?php

namespace App\Admin;

use App\Entity\Media;

final class MediaChoiceAttributes
{
    public static function label(Media $media, mixed $key = null, mixed $value = null): string
    {
        return (string) $media;
    }

    /** @return array<string, string> */
    public static function attributes(Media $media, mixed $key = null, mixed $value = null): array
    {
        return [
            'data-thumbnail' => $media->getThumbnailPath(),
            'data-label' => (string) $media,
        ];
    }
}
