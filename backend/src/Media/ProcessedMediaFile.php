<?php

namespace App\Media;

final class ProcessedMediaFile
{
    public function __construct(
        public readonly string $path,
        public readonly string $mimeType,
        public readonly ?int $width,
        public readonly ?int $height,
    ) {
    }
}
