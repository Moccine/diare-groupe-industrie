<?php

namespace App\Admin\Help;

final readonly class AdminHelp
{
    private const TONES = ['info', 'prepare', 'flow', 'example', 'warning', 'result', 'link'];

    /**
     * @param list<AdminHelpSection> $sections
     */
    public function __construct(
        public string $title,
        public array $sections,
    ) {
    }

    public function markup(): string
    {
        $html = '';
        foreach ($this->sections as $section) {
            $tone = in_array($section->tone, self::TONES, true) ? $section->tone : 'info';
            $icon = preg_match('/^fa-[a-z0-9-]+$/', $section->icon) === 1 ? $section->icon : 'fa-circle-info';
            $html .= '<section class="dgi-help-block dgi-help-block--'.$tone.'">';
            $html .= '<h3><span class="dgi-help-ico" aria-hidden="true"><i class="fa '.$this->escape($icon).'"></i></span><span>'.$this->escape($section->heading).'</span></h3>';
            foreach ($section->paragraphs as $paragraph) {
                $html .= '<p>'.$this->escape($paragraph).'</p>';
            }
            if ($section->items !== []) {
                $tag = $section->ordered ? 'ol' : 'ul';
                $html .= '<'.$tag.'>';
                foreach ($section->items as $item) {
                    $html .= '<li>'.$this->escape($item).'</li>';
                }
                $html .= '</'.$tag.'>';
            }
            $html .= '</section>';
        }

        return $html;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
