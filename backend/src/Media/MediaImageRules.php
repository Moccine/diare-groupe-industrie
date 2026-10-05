<?php

namespace App\Media;

final class MediaImageRules
{
    public const MAX_BYTES = 8_388_608;
    public const MAX_EDGE = 2000;

    /** @var list<string> */
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
    ];
}
