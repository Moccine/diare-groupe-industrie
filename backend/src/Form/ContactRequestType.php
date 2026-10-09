<?php

namespace App\Form;

use App\Entity\ContactRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ContactRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'family-name',
                    'maxlength' => 80,
                    'placeholder' => 'Votre nom',
                ],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'given-name',
                    'maxlength' => 80,
                    'placeholder' => 'Votre prénom',
                ],
            ])
            ->add('company', TextType::class, [
                'label' => 'Entreprise',
                'required' => false,
                'attr' => [
                    'autocomplete' => 'organization',
                    'maxlength' => 160,
                    'placeholder' => 'Nom de votre entreprise',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'email',
                    'maxlength' => 180,
                    'placeholder' => 'vous@exemple.com',
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'autocomplete' => 'tel',
                    'maxlength' => 40,
                    'placeholder' => '+224 6XX XX XX XX',
                ],
            ])
            ->add('subject', TextType::class, [
                'label' => 'Objet',
                'empty_data' => '',
                'attr' => [
                    'maxlength' => 180,
                    'placeholder' => 'Objet de votre demande',
                ],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'empty_data' => '',
                'attr' => [
                    'rows' => 6,
                    'minlength' => 10,
                    'maxlength' => 5000,
                    'placeholder' => 'Décrivez votre demande…',
                ],
            ])
            ->add('consent', CheckboxType::class, [
                'label' => 'J’accepte que ces informations soient utilisées pour répondre à ma demande.',
            ])
            ->add('website', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Site web',
                'attr' => [
                    'class' => 'hp',
                    'tabindex' => '-1',
                    'autocomplete' => 'off',
                ],
                'row_attr' => ['class' => 'visually-hidden', 'aria-hidden' => 'true'],
            ])
            ->add('productSlug', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'label' => false,
            ])
            ->add('recaptchaToken', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'label' => false,
                'attr' => ['data-recaptcha-token' => '1'],
                'row_attr' => ['class' => 'visually-hidden'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactRequest::class,
            'csrf_protection' => true,
        ]);
    }
}
