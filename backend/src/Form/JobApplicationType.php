<?php

namespace App\Form;

use App\Entity\JobApplication;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class JobApplicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['spontaneous'] === true) {
            $builder->add('desiredRole', TextType::class, [
                'label' => 'Poste ou domaine recherché',
                'empty_data' => '',
                'attr' => [
                    'maxlength' => 120,
                    'placeholder' => 'Ex. : Commercial terrain',
                ],
                'constraints' => [
                    new NotBlank(message: 'Indiquez le poste ou le domaine recherché.', normalizer: 'trim'),
                    new Length(max: 120),
                ],
            ]);
        }

        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'given-name',
                    'maxlength' => 100,
                    'placeholder' => 'Votre prénom',
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'family-name',
                    'maxlength' => 100,
                    'placeholder' => 'Votre nom',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'email',
                    'maxlength' => 180,
                    'placeholder' => 'vous@exemple.com',
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'tel',
                    'maxlength' => 40,
                    'inputmode' => 'tel',
                    'pattern' => '[+0-9][0-9\\s\\(\\)\\.\\/\\-]{6,39}',
                    'placeholder' => '+224 6XX XX XX XX',
                ],
            ])
            ->add('linkedinUrl', UrlType::class, [
                'label' => 'Profil LinkedIn',
                'required' => false,
                'default_protocol' => 'https',
                'attr' => [
                    'maxlength' => 500,
                    'placeholder' => 'https://www.linkedin.com/in/',
                    'inputmode' => 'url',
                ],
            ])
            ->add('availability', TextType::class, [
                'label' => 'Disponibilité',
                'required' => false,
                'help' => 'Facultatif. Par exemple : immédiate, ou sous un mois.',
                'attr' => [
                    'maxlength' => 120,
                    'placeholder' => 'Ex. : immédiate, ou sous un mois',
                ],
            ])
            ->add('cv', FileType::class, [
                'label' => 'CV',
                'mapped' => false,
                'constraints' => [
                    new NotBlank(message: 'Le CV est obligatoire.'),
                    new File(
                        maxSize: JobApplication::MAX_CV_BYTES,
                        mimeTypes: ['application/pdf', 'application/x-pdf'],
                        extensions: ['pdf'],
                        mimeTypesMessage: 'Le fichier sélectionné n’est pas un PDF valide.',
                        maxSizeMessage: 'Le CV ne doit pas dépasser 5 Mo.',
                        disallowEmptyMessage: 'Le fichier CV est vide.',
                        extensionsMessage: 'Le CV doit être un fichier PDF.',
                        uploadIniSizeErrorMessage: 'Le CV ne doit pas dépasser 5 Mo.',
                    ),
                ],
                'attr' => [
                    'accept' => 'application/pdf,.pdf',
                ],
            ])
            ->add('motivation', TextareaType::class, [
                'label' => 'Votre motivation',
                'help' => 'Présentez brièvement votre expérience, vos compétences et les raisons pour lesquelles vous souhaitez rejoindre Diaré Groupe Industrie.',
                'empty_data' => '',
                'attr' => [
                    'rows' => 8,
                    'minlength' => 30,
                    'maxlength' => 2000,
                    'data-char-max' => '2000',
                    'aria-describedby' => 'job_application_motivation_help',
                    'placeholder' => 'Présentez votre parcours et votre motivation…',
                ],
            ])
            ->add('consent', CheckboxType::class, [
                'label' => false,
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new IsTrue(message: 'Le consentement est obligatoire.'),
                ],
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
            'data_class' => JobApplication::class,
            'csrf_protection' => true,
            'spontaneous' => false,
        ]);
        $resolver->setAllowedTypes('spontaneous', 'bool');
    }

    public function getBlockPrefix(): string
    {
        return 'job_application';
    }
}
