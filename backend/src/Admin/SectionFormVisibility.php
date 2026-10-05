<?php

namespace App\Admin;

use App\Enum\SectionType;

/**
 * Champs réellement lus par les modèles de contenu.
 * Un champ absent de cette liste reste toujours affiché.
 */
final class SectionFormVisibility
{
    /** @var array<string, list<string>> */
    private const FIELDS = [
        'subtitle' => ['text_image', 'products', 'statistics', 'mission', 'vision', 'values', 'distribution', 'partners', 'news', 'gallery', 'activities', 'custom'],
        'content' => ['hero', 'text_image', 'statistics', 'mission', 'vision', 'values', 'distribution', 'cta', 'quality', 'activities', 'custom'],
        'image' => ['hero', 'text_image', 'gallery'],
        'backgroundImage' => ['text_image', 'mission', 'vision', 'distribution', 'cta', 'quality'],
        'buttonLabel' => ['hero', 'text_image', 'products', 'distribution', 'news', 'cta'],
        'buttonUrl' => ['hero', 'text_image', 'products', 'distribution', 'news', 'cta'],
        'secondaryButtonLabel' => ['hero'],
        'secondaryButtonUrl' => ['hero'],
        'items' => ['values', 'distribution', 'gallery', 'activities', 'custom'],
        'theme' => ['text_image', 'products', 'statistics', 'mission', 'vision', 'values', 'distribution', 'partners', 'news', 'cta', 'gallery', 'quality', 'activities', 'custom'],
    ];

    public static function cssClass(string $field): string
    {
        $types = self::FIELDS[$field] ?? null;
        if ($types === null) {
            return '';
        }

        $classes = ['dgi-sec-field'];
        foreach ($types as $type) {
            $classes[] = 'dgi-when-'.$type;
        }

        return implode(' ', $classes);
    }

    /** @return list<string> */
    public static function fields(): array
    {
        return array_keys(self::FIELDS);
    }

    /** @return list<string> */
    public static function typesFor(string $field): array
    {
        return self::FIELDS[$field] ?? [];
    }

    public static function coversKnownTypes(): bool
    {
        $known = array_map(static fn (SectionType $type): string => $type->value, SectionType::cases());
        foreach (self::FIELDS as $types) {
            foreach ($types as $type) {
                if (!in_array($type, $known, true)) {
                    return false;
                }
            }
        }

        return true;
    }
}
