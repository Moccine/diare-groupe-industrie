<?php

namespace App\Form\Extension;

use EasyCorp\Bundle\EasyAdminBundle\Form\Type\CrudAutocompleteType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CrudAutocompleteChoiceAttrExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [CrudAutocompleteType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined('choice_attr');
        $resolver->setAllowedTypes('choice_attr', ['null', 'array', 'callable', \Closure::class]);
    }
}
