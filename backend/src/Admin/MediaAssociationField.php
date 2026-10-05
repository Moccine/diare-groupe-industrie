<?php

namespace App\Admin;

use App\Controller\Admin\Crud\MediaCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class MediaAssociationField
{
    public static function new(string $property, ?string $label = null): AssociationField
    {
        return AssociationField::new($property, $label)
            ->setCrudController(MediaCrudController::class)
            ->autocomplete(true, null, 'admin/field/media_autocomplete.html.twig', true)
            ->setFormTypeOption('choice_label', MediaChoiceAttributes::label(...))
            ->setFormTypeOption('choice_attr', MediaChoiceAttributes::attributes(...))
            ->setFormTypeOption('attr.data-dgi-media-picker', '1');
    }

    public static function index(string $property, string $label = 'Miniature', bool $logoFallback = false): TextField
    {
        return TextField::new($property, $label)
            ->setTemplatePath($logoFallback
                ? 'admin/field/related_media_thumb_fallback.html.twig'
                : 'admin/field/related_media_thumb.html.twig')
            ->setSortable(false)
            ->onlyOnIndex();
    }
}
