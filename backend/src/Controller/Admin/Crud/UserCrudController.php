<?php

namespace App\Controller\Admin\Crud;

use App\Admin\FormColumns;
use App\Entity\User;
use App\Security\PasswordPolicy;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Administrateur')
            ->setEntityLabelInPlural('Administrateurs')
            ->setSearchFields(['fullName', 'email'])
            ->setHelp(Crud::PAGE_NEW, 'Un administrateur peut se connecter à cette interface de gestion.')
            ->setHelp(Crud::PAGE_EDIT, 'Un administrateur peut se connecter à cette interface de gestion.');
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Identité', 'fa fa-user-shield');
        yield TextField::new('fullName', 'Nom')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Nom affiché dans l’administration.');
        yield EmailField::new('email', 'Email')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Identifiant utilisé pour se connecter. Il n’est pas affiché sur le site.');

        $isNew = $pageName === Crud::PAGE_NEW;
        yield FormField::addFieldset('Mot de passe', 'fa fa-key')
            ->setHelp('Le mot de passe n’est jamais réaffiché après l’enregistrement. L’œil l’affiche le temps de le copier. Le bouton « Générer » remplit aussi la confirmation.');
        yield Field::new('plainPassword', false)
            ->setColumns(FormColumns::MEDIUM)
            ->setFormType(RepeatedType::class)
            ->setFormTypeOptions([
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => $isNew,
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                'first_options' => [
                    'label' => $isNew ? 'Mot de passe' : 'Nouveau mot de passe',
                    'help' => $isNew
                        ? 'Générez-le ou saisissez-le, puis confirmez-le. L’œil l’affiche le temps de le copier.'
                        : 'Laissez les deux champs vides pour conserver le mot de passe actuel.',
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'data-dgi-password-generator' => '1',
                        'data-password-policy' => '1',
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmation',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'constraints' => PasswordPolicy::constraints($isNew),
            ])
            ->onlyOnForms();
    }

    public function persistEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $this->applyPassword($entityInstance, true);
            $entityInstance->setRoles(['ROLE_ADMIN']);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $this->applyPassword($entityInstance, false);
            if (!in_array('ROLE_ADMIN', $entityInstance->getRoles(), true)) {
                $entityInstance->setRoles(['ROLE_ADMIN']);
            }
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function applyPassword(User $user, bool $required): void
    {
        $payload = $this->getContext()?->getRequest()->request->all('User');
        $submitted = is_array($payload) ? ($payload['plainPassword'] ?? null) : null;
        $plain = '';
        if (is_string($submitted)) {
            $plain = $submitted;
        } elseif (is_array($submitted)) {
            $first = (string) ($submitted['first'] ?? '');
            $second = (string) ($submitted['second'] ?? '');
            $plain = hash_equals($first, $second) ? $first : '';
        }

        if ($plain === '') {
            if ($required) {
                throw new \RuntimeException('Le mot de passe est obligatoire.');
            }

            return;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plain));
    }
}
