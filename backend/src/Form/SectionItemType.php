<?php

namespace App\Form;

use App\Admin\MediaChoiceAttributes;
use App\Entity\Media;
use App\Entity\SectionItem;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SectionItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'help' => 'Titre de la ligne, par exemple une activité, une valeur ou un point de distribution.',
            ])
            ->add('text', TextareaType::class, [
                'label' => 'Texte',
                'required' => false,
                'help' => 'Précision affichée sous le titre de la ligne.',
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Position',
                'help' => 'Ordre de la ligne. Par exemple, 1 apparaît avant 2, puis 3.',
            ])
            ->add('image', EntityType::class, [
                'class' => Media::class,
                'label' => 'Image',
                'required' => false,
                'placeholder' => 'Aucune image',
                'help' => 'Image de la ligne. Elle est affichée dans une galerie. Ajoutez-la d’abord dans la bibliothèque d’images.',
                'choice_label' => MediaChoiceAttributes::label(...),
                'choice_attr' => MediaChoiceAttributes::attributes(...),
                'attr' => [
                    'data-ea-widget' => 'ea-autocomplete',
                    'data-dgi-media-picker' => '1',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SectionItem::class,
        ]);
    }
}
