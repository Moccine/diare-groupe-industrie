<?php

namespace App\Controller\Admin\Crud;

use App\Admin\FormColumns;
use App\Entity\ProductCategory;
use App\Enum\CategoryAccent;
use App\Enum\CategoryIcon;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\EnumType;

final class ProductCategoryCrudController extends AbstractCrudController
{
    private const SLUG_UNLOCK = 'Modifier cette adresse change le lien du filtre. Les adresses déjà partagées ne fonctionneront plus. Voulez-vous continuer ?';

    public static function getEntityFqcn(): string
    {
        return ProductCategory::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Catégorie de produits')
            ->setEntityLabelInPlural('Catégories de produits')
            ->setDefaultSort(['position' => 'ASC'])
            ->setSearchFields(['name', 'slug'])
            ->setHelp(Crud::PAGE_NEW, 'Les catégories servent à organiser les produits et à proposer des filtres aux visiteurs du catalogue.')
            ->setHelp(Crud::PAGE_EDIT, 'Les catégories servent à organiser les produits et à proposer des filtres aux visiteurs du catalogue.');
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Informations', 'fa fa-tag')
            ->setHelp('Une catégorie regroupe plusieurs produits et apparaît comme filtre dans le catalogue.');
        yield TextField::new('name', 'Nom')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Nom du filtre et de la pastille affichée sur les cartes produits.');
        yield TextareaField::new('description', 'Description')
            ->setColumns(FormColumns::LARGE)
            ->setNumOfRows(4)
            ->setHelp('Note interne. Ce texte n’est pas affiché sur le catalogue.')
            ->hideOnIndex();

        yield FormField::addFieldset('Présentation', 'fa fa-palette');
        yield ChoiceField::new('icon', 'Icône')
            ->setColumns(FormColumns::SMALL)
            ->setFormType(EnumType::class)
            ->setFormTypeOptions([
                'class' => CategoryIcon::class,
                'choice_label' => static fn (CategoryIcon $icon): string => $icon->label(),
            ])
            ->setHelp('Petit pictogramme affiché avec le filtre.')
            ->hideOnIndex();
        yield ChoiceField::new('accentColor', 'Repère de couleur')
            ->setColumns(FormColumns::SMALL)
            ->setFormType(EnumType::class)
            ->setFormTypeOptions([
                'class' => CategoryAccent::class,
                'choice_label' => static fn (CategoryAccent $accent): string => $accent->label(),
            ])
            ->setHelp('Couleur de la pastille du filtre. Elle ne sert pas de couleur de texte.')
            ->hideOnIndex();
        yield IntegerField::new('position', 'Position')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('Ordre des filtres. Par exemple, 1 apparaît avant 2, puis 3.');

        yield FormField::addFieldset('Publication', 'fa fa-eye');
        yield BooleanField::new('isPublished', 'Publiée')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('Une catégorie masquée n’apparaît plus dans les filtres du catalogue. Ses produits peuvent rester visibles dans « Tout ».');

        yield FormField::addFieldset('Adresse', 'fa fa-link')
            ->setHelp('Fin du lien de filtre. Exemple : produits-laitiers pour la catégorie « Produits laitiers ».');
        yield SlugField::new('slug', 'Adresse URL')
            ->setTargetFieldName('name')
            ->setUnlockConfirmationMessage(self::SLUG_UNLOCK)
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Partie visible du lien de filtre. Ne la changez pas si ce lien a déjà été partagé.')
            ->hideOnIndex();
    }
}
