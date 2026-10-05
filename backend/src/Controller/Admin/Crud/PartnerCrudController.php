<?php

namespace App\Controller\Admin\Crud;

use App\Admin\FormColumns;
use App\Entity\Partner;
use App\Service\AdminLinkFactory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

final class PartnerCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminLinkFactory $adminLinks,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Partner::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Partenaire')
            ->setEntityLabelInPlural('Partenaires')
            ->setDefaultSort(['position' => 'ASC'])
            ->setSearchFields(['name'])
            ->setHelp(Crud::PAGE_NEW, 'Les partenaires publiés apparaissent dans les blocs Partenaires des pages qui en ont un.')
            ->setHelp(Crud::PAGE_EDIT, 'Les partenaires publiés apparaissent dans les blocs Partenaires des pages qui en ont un.');
    }

    public function configureFields(string $pageName): iterable
    {
        $library = $this->adminLinks->anchor(MediaCrudController::class, 'bibliothèque d’images');

        yield FormField::addFieldset('Partenaire', 'fa fa-handshake')
            ->setHelp('Le logo se choisit dans la '.$library.'.');
        yield TextField::new('name', 'Nom')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Nom du partenaire. Il sert aussi de texte de remplacement si le logo est absent.');
        yield AssociationField::new('logo', 'Logo')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Logo affiché dans les blocs Partenaires du site. Ajoutez-le d’abord dans la bibliothèque d’images.');
        yield UrlField::new('url', 'Site')
            ->setColumns(FormColumns::LARGE)
            ->setHelp('Adresse du site internet du partenaire. Exemple : https://exemple.com. Si elle est renseignée, le logo devient un lien.')
            ->hideOnIndex();

        yield FormField::addFieldset('Affichage', 'fa fa-eye');
        yield BooleanField::new('isVisible', 'Publié')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('Un partenaire non publié n’apparaît plus sur le site. Il reste enregistré ici.');
        yield IntegerField::new('position', 'Position')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('Ordre d’affichage des logos. Par exemple, 1 apparaît avant 2, puis 3.');
    }
}
