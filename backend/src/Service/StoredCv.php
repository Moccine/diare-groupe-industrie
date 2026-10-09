<?php

namespace App\Service;

final readonly class StoredCv
{
    public function __construct(
        public string $storedFilename,
        public string $originalFilename,
        public string $mimeType,
        public int $size,
    ) {
    }
}
