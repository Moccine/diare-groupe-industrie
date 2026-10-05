<?php

namespace App\Media;

use App\Entity\Media;

final class PageBannerSpec
{
    public const MIN_WIDTH = 1600;
    public const MIN_HEIGHT = 480;
    public const RECOMMENDED_WIDTH = 1920;
    public const RECOMMENDED_HEIGHT = 640;

    public static function violation(?Media $media): ?string
    {
        if (!$media instanceof Media) {
            return null;
        }

        $width = $media->getWidth();
        $height = $media->getHeight();
        if ($width === null || $height === null || $width <= 0 || $height <= 0) {
            return 'Les dimensions de cette image ne sont pas connues. Téléversez-la à nouveau dans la bibliothèque d’images.';
        }

        if ($width < $height) {
            return sprintf(
                'La bannière doit être une image horizontale. Minimum %d × %d px. Recommandé : %d × %d px.',
                self::MIN_WIDTH,
                self::MIN_HEIGHT,
                self::RECOMMENDED_WIDTH,
                self::RECOMMENDED_HEIGHT,
            );
        }

        if ($width < self::MIN_WIDTH || $height < self::MIN_HEIGHT) {
            return sprintf(
                'Cette image est trop petite pour une bannière (%d × %d px). Le minimum est %d × %d px. Recommandé : %d × %d px.',
                $width,
                $height,
                self::MIN_WIDTH,
                self::MIN_HEIGHT,
                self::RECOMMENDED_WIDTH,
                self::RECOMMENDED_HEIGHT,
            );
        }

        return null;
    }
}
