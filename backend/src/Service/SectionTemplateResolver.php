<?php

namespace App\Service;

use App\Enum\SectionType;

final class SectionTemplateResolver
{
    public function __construct(
        private readonly string $templatesDir,
    ) {
    }

    public function resolve(SectionType $type): ?string
    {
        $relative = 'sections/_'.$type->value.'.html.twig';
        $path = rtrim($this->templatesDir, '/').'/'.$relative;

        return is_file($path) ? $relative : null;
    }
}
