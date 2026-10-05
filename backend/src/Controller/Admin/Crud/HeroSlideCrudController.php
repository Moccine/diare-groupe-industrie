<?php

namespace App\Controller\Admin\Crud;

use App\Admin\FormColumns;
use App\Entity\HeroSlide;
use App\Enum\HeroContentPosition;
use App\Enum\HeroOverlay;
use App\Enum\SectionType;
use App\Service\AdminLinkFactory;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\EnumType;

final class HeroSlideCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminLinkFactory $adminLinks,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return HeroSlide::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Bannière d’accueil')
            ->setEntityLabelInPlural('Bannières d’accueil')
            ->setDefaultSort(['section' => 'ASC', 'position' => 'ASC'])
            ->setSearchFields(['title', 'eyebrow', 'description'])
            ->setHelp(Crud::PAGE_NEW, 'Les bannières sont les grandes images qui défilent en haut des pages utilisant un bloc Bannière. Seules les bannières actives, avec une image, sont affichées.')
            ->setHelp(Crud::PAGE_EDIT, 'Les bannières sont les grandes images qui défilent en haut des pages utilisant un bloc Bannière. Seules les bannières actives, avec une image, sont affichées.');
    }

    public function configureFields(string $pageName): iterable
    {
        $sections = $this->adminLinks->anchor(SectionCrudController::class, 'Contenus des pages');
        $library = $this->adminLinks->anchor(MediaCrudController::class, 'bibliothèque d’images');

        yield FormField::addFieldset('Emplacement et ordre', 'fa fa-location-dot')
            ->setHelp('Une bannière doit être rattachée à un contenu de page de type Bannière. S’il n’en existe pas, créez-le d’abord dans '.$sections.'.');
        yield AssociationField::new('section', 'Bloc bannière')
            ->setQueryBuilder(static function (QueryBuilder $queryBuilder): QueryBuilder {
                return $queryBuilder
                    ->andWhere('entity.type = :type')
                    ->setParameter('type', SectionType::Hero);
            })
            ->setColumns(FormColumns::HALF_ON_DESKTOP)
            ->setHelp('Contenu de page de type Bannière dans lequel cette image défile.');
        yield IntegerField::new('position', 'Ordre')
            ->setColumns(FormColumns::QUARTER)
            ->setHelp('Ordre de passage. Par exemple, 1 apparaît avant 2, puis 3.');
        yield BooleanField::new('isActive', 'Active')
            ->setColumns(FormColumns::QUARTER)
            ->setHelp('Seules les bannières actives apparaissent. Une bannière inactive reste enregistrée.');

        yield FormField::addFieldset('Texte', 'fa fa-align-left');
        yield TextField::new('eyebrow', 'Surtitre')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Courte ligne affichée au-dessus du titre. Laissez vide pour ne pas l’afficher.')
            ->hideOnIndex();
        yield TextField::new('title', 'Titre')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Titre principal affiché en grand sur la bannière.');
        yield TextareaField::new('description', 'Description')
            ->setColumns(FormColumns::LARGE)
            ->setNumOfRows(6)
            ->setHelp('Texte affiché sous le titre. Laissez vide pour n’afficher que le titre.')
            ->hideOnIndex();

        yield FormField::addFieldset('Images', 'fa fa-image')
            ->setHelp('Choisissez les images dans la '.$library.'. Sans image pour grand écran, la bannière n’est pas affichée.');
        yield AssociationField::new('image', 'Image pour grand écran')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Image de fond sur ordinateur. Ajoutez-la d’abord dans la bibliothèque d’images.')
            ->hideOnIndex();
        yield AssociationField::new('mobileImage', 'Image pour téléphone')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Image utilisée sur téléphone. Si elle est vide, l’image pour grand écran est utilisée.')
            ->hideOnIndex();

        yield FormField::addFieldset('Bouton principal', 'fa fa-hand-pointer');
        yield TextField::new('buttonLabel', 'Texte du bouton')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Texte du bouton principal. Laissez vide pour ne pas afficher de bouton.')
            ->hideOnIndex();
        yield TextField::new('buttonUrl', 'Lien du bouton')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Page ouverte lorsque le visiteur clique. Exemple pour une page du site : /nos-produits.')
            ->hideOnIndex();

        yield FormField::addFieldset('Bouton secondaire', 'fa fa-hand-pointer');
        yield TextField::new('secondaryButtonLabel', 'Texte du bouton')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Texte du second bouton. Laissez vide pour ne pas l’afficher.')
            ->hideOnIndex();
        yield TextField::new('secondaryButtonUrl', 'Lien du bouton')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Page ouverte par le second bouton. Exemple : /contact.')
            ->hideOnIndex();

        yield FormField::addFieldset('Présentation', 'fa fa-paintbrush');
        yield ChoiceField::new('contentPosition', 'Position du texte')
            ->setFormType(EnumType::class)
            ->setFormTypeOptions([
                'class' => HeroContentPosition::class,
                'choice_label' => static fn (HeroContentPosition $position): string => $position->label(),
            ])
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Place le texte à gauche, au centre ou à droite de la bannière.')
            ->hideOnIndex();
        yield ChoiceField::new('overlay', 'Assombrissement de l’image')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Ajoute un voile sur l’image afin que le texte reste lisible. Léger, moyen ou soutenu.')
            ->setFormType(EnumType::class)
            ->setFormTypeOptions([
                'class' => HeroOverlay::class,
                'choice_label' => static fn (HeroOverlay $overlay): string => $overlay->label(),
            ])
            ->hideOnIndex();
    }
}
