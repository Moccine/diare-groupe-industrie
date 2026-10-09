<?php

namespace App\Service;

final class SeoDocument
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $canonicalPath,
        public readonly ?string $imagePath = null,
        public readonly string $type = 'website',
        public readonly ?string $jsonLd = null,
        public readonly bool $noindex = false,
    ) {
    }
}
