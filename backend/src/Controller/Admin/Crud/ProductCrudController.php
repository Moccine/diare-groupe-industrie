<?php

namespace App\Controller\Admin\Crud;

use App\Admin\FormColumns;
use App\Admin\PublicSiteAction;
use App\Entity\Product;
use App\Service\AdminLinkFactory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ProductCrudController extends AbstractCrudController
{
    private const SLUG_UNLOCK = 'Modifier cette adresse change le lien déjà en ligne. Les adresses partagées ne fonctionneront plus. Voulez-vous continuer ?';

    public function __construct(
        private readonly AdminLinkFactory $adminLinks,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return PublicSiteAction::add($actions, $this->publicUrl(...));
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Produit')
            ->setEntityLabelInPlural('Produits')
            ->setDefaultSort(['position' => 'ASC'])
            ->setSearchFields(['name', 'slug', 'shortDescription'])
            ->setHelp(Crud::PAGE_NEW, 'Un produit apparaît dans le catalogue du site lorsqu’il est publié. Vous pouvez aussi le mettre en avant sur la page d’accueil.')
            ->setHelp(Crud::PAGE_EDIT, 'Un produit apparaît dans le catalogue du site lorsqu’il est publié. Vous pouvez aussi le mettre en avant sur la page d’accueil.');
    }

    public function configureFields(string $pageName): iterable
    {
        $categories = $this->adminLinks->anchor(ProductCategoryCrudController::class, 'Catégories de produits');
        $library = $this->adminLinks->anchor(MediaCrudController::class, 'bibliothèque d’images');

        yield FormField::addFieldset('Informations principales', 'fa fa-box')
            ->setHelp('La catégorie se crée d’abord dans '.$categories.'.');
        yield TextField::new('name', 'Nom')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Nom affiché dans le catalogue et sur la fiche du produit.');
        yield AssociationField::new('category', 'Catégorie')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Classe le produit dans une famille et permet aux visiteurs de filtrer le catalogue. Si la catégorie n’existe pas encore, créez-la d’abord dans « Catégories de produits ».');
        yield TextareaField::new('shortDescription', 'Accroche')
            ->setColumns(FormColumns::LARGE)
            ->setNumOfRows(4)
            ->setHelp('Texte court visible dans la carte produit, avant d’ouvrir la fiche complète.')
            ->hideOnIndex();
        yield TextareaField::new('description', 'Description')
            ->setColumns(FormColumns::LARGE)
            ->setNumOfRows(8)
            ->setHelp('Présentation détaillée visible sur la fiche du produit.')
            ->hideOnIndex();

        yield FormField::addFieldset('Images', 'fa fa-image')
            ->setHelp('Les images se choisissent dans la '.$library.'. Sans image principale, le logo DGI s’affiche à la place.');
        yield AssociationField::new('mainImage', 'Image principale')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Image utilisée dans le catalogue et sur la fiche produit. Ajoutez-la d’abord dans la bibliothèque d’images.')
            ->hideOnIndex();
        yield AssociationField::new('gallery', 'Galerie')
            ->setColumns(FormColumns::LARGE)
            ->setHelp('Images supplémentaires affichées sur la fiche du produit.')
            ->hideOnIndex();

        yield FormField::addFieldset('Mise en avant et publication', 'fa fa-bullhorn');
        yield BooleanField::new('isFeatured', 'Mis en avant')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('Affiche aussi ce produit dans la sélection de produits de la page d’accueil.');
        yield BooleanField::new('isPublished', 'Publié')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('Lorsqu’il est publié, le produit devient visible dans le catalogue. Désactivé, il reste enregistré dans l’administration.');
        yield IntegerField::new('position', 'Position')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('Ordre d’affichage. Par exemple, 1 apparaît avant 2, puis 3.');

        yield FormField::addFieldset('Adresse de la fiche', 'fa fa-link')
            ->setHelp('Fin du lien de la fiche, par exemple lait-entier-1-l. Ne la changez pas si le lien a déjà été partagé.');
        yield SlugField::new('slug', 'Adresse URL')
            ->setTargetFieldName('name')
            ->setUnlockConfirmationMessage(self::SLUG_UNLOCK)
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Partie visible du lien de la fiche. Elle se remplit à partir du nom. Ne la changez pas après avoir partagé le produit.')
            ->hideOnIndex();

        yield FormField::addFieldset('Référencement Google', 'fa fa-magnifying-glass')
            ->setHelp('Ces champs choisissent le titre et le texte qui peuvent apparaître dans Google. S’ils sont vides, le nom et l’accroche sont utilisés.');
        yield TextField::new('metaTitle', 'Titre Google')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Titre qui peut apparaître dans Google. S’il est vide, le nom du produit est utilisé.')
            ->hideOnIndex();
        yield TextareaField::new('metaDescription', 'Description Google')
            ->setColumns(FormColumns::LARGE)
            ->setNumOfRows(4)
            ->setHelp('Texte qui peut apparaître sous le titre dans Google. S’il est vide, l’accroche est utilisée.')
            ->hideOnIndex();
    }

    private function publicUrl(Product $product): ?string
    {
        if (!$product->isPublished() || $product->getSlug() === '') {
            return null;
        }

        return $this->urlGenerator->generate('product_show', ['slug' => $product->getSlug()]);
    }
}
