<?php

namespace App\Controller\Admin\Crud;

use App\Admin\FormColumns;
use App\Entity\ContactRequest;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class ContactRequestCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ContactRequest::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Message reçu')
            ->setEntityLabelInPlural('Messages reçus')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setDefaultRowAction(Action::DETAIL)
            ->overrideTemplate('crud/index', 'admin/crud/contact_index.html.twig')
            ->setSearchFields(['lastName', 'firstName', 'email', 'subject', 'company'])
            ->setHelp(Crud::PAGE_INDEX, 'Messages envoyés depuis le formulaire de contact. Ils ne sont pas publiés sur le site.')
            ->setHelp(Crud::PAGE_EDIT, 'Message reçu depuis le formulaire de contact. Il n’est pas affiché sur le site. Cochez « Lu » lorsqu’il a été traité.')
            ->setHelp(Crud::PAGE_DETAIL, 'Message reçu depuis le formulaire de contact. Il n’est pas affiché sur le site.');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('fullName', 'Nom')
            ->setSortable(false)
            ->onlyOnIndex();

        yield FormField::addFieldset('Expéditeur', 'fa fa-user');
        yield TextField::new('firstName', 'Prénom')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Prénom saisi dans le formulaire.')
            ->hideOnIndex();
        yield TextField::new('lastName', 'Nom')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Nom saisi dans le formulaire.')
            ->hideOnIndex();
        yield TextField::new('company', 'Entreprise')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Société indiquée, si la personne en a saisi une.')
            ->hideOnIndex();
        yield EmailField::new('email', 'Email')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Adresse à utiliser pour répondre. Elle n’est pas affichée sur le site.');
        yield TelephoneField::new('phone', 'Téléphone')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Numéro indiqué dans le formulaire, s’il a été saisi.')
            ->hideOnIndex();

        yield FormField::addFieldset('Message', 'fa fa-envelope');
        yield TextField::new('subject', 'Objet')
            ->setColumns(FormColumns::LARGE)
            ->setHelp('Objet choisi ou saisi dans le formulaire.');
        yield TextareaField::new('message', 'Message')
            ->setColumns(FormColumns::LARGE)
            ->setNumOfRows(8)
            ->setHelp('Texte envoyé par la personne.')
            ->hideOnIndex();
        yield BooleanField::new('consent', 'Consentement')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('Case de consentement cochée lors de l’envoi.')
            ->renderAsSwitch(false)
            ->hideOnForm()
            ->hideOnIndex();

        yield FormField::addFieldset('Suivi', 'fa fa-check');
        yield BooleanField::new('isRead', 'Lu')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('Cochez lorsque le message a été traité. Cela retire le badge du menu.');
        yield DateTimeField::new('createdAt', 'Reçu le')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Date et heure de réception.')
            ->hideOnForm();
    }
}
