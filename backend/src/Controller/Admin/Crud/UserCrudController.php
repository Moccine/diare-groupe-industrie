<?php

namespace App\Controller\Admin\Crud;

use App\Admin\FormColumns;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
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

        yield FormField::addFieldset('Mot de passe', 'fa fa-key')
            ->setHelp('Le mot de passe n’est jamais réaffiché après l’enregistrement. Choisissez-en un long et difficile à deviner.');
        yield Field::new('plainPassword', $pageName === Crud::PAGE_NEW ? 'Mot de passe' : 'Nouveau mot de passe')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp($pageName === Crud::PAGE_NEW
                ? 'Mot de passe de la première connexion. Il n’est pas affiché ensuite.'
                : 'Laissez vide pour conserver le mot de passe actuel. S’il est renseigné, il remplace l’ancien et n’est pas réaffiché.')
            ->setFormType(PasswordType::class)
            ->setFormTypeOptions([
                'mapped' => false,
                'required' => $pageName === Crud::PAGE_NEW,
                'attr' => ['autocomplete' => 'new-password'],
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
        $plain = is_array($payload) ? (string) ($payload['plainPassword'] ?? '') : '';

        if ($plain === '') {
            if ($required) {
                throw new \RuntimeException('Le mot de passe est obligatoire.');
            }

            return;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plain));
    }
}
