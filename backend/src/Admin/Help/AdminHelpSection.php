<?php

namespace App\Admin\Help;

final readonly class AdminHelpSection
{
    /**
     * @param list<string> $paragraphs
     * @param list<string> $items
     */
    public function __construct(
        public string $heading,
        public array $paragraphs = [],
        public array $items = [],
        public string $icon = 'fa-circle-info',
        public string $tone = 'info',
        public bool $ordered = false,
    ) {
    }
}
