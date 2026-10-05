<?php

namespace App\Controller\Admin\Crud;

use App\Admin\FormColumns;
use App\Admin\MediaAssociationField;
use App\Admin\SectionFormVisibility;
use App\Entity\Section;
use App\Enum\SectionTheme;
use App\Enum\SectionType;
use App\Form\SectionItemType;
use App\Service\AdminLinkFactory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\Form\Extension\Core\Type\EnumType;

final class SectionCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminLinkFactory $adminLinks,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Section::class;
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addJsFile('admin/section-form.js');
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Contenu de page')
            ->setEntityLabelInPlural('Contenus des pages')
            ->setDefaultSort(['page' => 'ASC', 'position' => 'ASC'])
            ->setSearchFields(['title', 'eyebrow', 'content'])
            ->setHelp(Crud::PAGE_NEW, 'Chaque contenu appartient à une page. Son type détermine sa présentation sur le site. Les champs inutiles pour ce type sont masqués, sans effacer ce qui est déjà enregistré.')
            ->setHelp(Crud::PAGE_EDIT, 'Chaque contenu appartient à une page. Son type détermine sa présentation sur le site. Les champs inutiles pour ce type sont masqués, sans effacer ce qui est déjà enregistré.');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(EntityFilter::new('page', 'Page'));
    }

    public function configureFields(string $pageName): iterable
    {
        $hints = [];
        foreach (SectionType::cases() as $type) {
            $hints[$type->value] = $type->guide();
        }

        $pages = $this->adminLinks->anchor(PageCrudController::class, 'Pages du site');
        $banners = $this->adminLinks->anchor(HeroSlideCrudController::class, 'Bannières d’accueil');
        $library = $this->adminLinks->anchor(MediaCrudController::class, 'bibliothèque d’images');

        yield FormField::addFieldset('Emplacement', 'fa fa-location-dot')
            ->setHelp($this->typeGuide().'<p>Le contenu doit être rattaché à une page existante. Les pages se créent dans '.$pages.'. Les images du bandeau se gèrent dans '.$banners.'.</p><p class="dgi-type-live" aria-live="polite"></p>')
            ->setFormTypeOption('attr', [
                'data-section-hints' => json_encode($hints, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ]);
        yield AssociationField::new('page', 'Page')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Page du site dans laquelle ce bloc sera affiché.');
        yield ChoiceField::new('type', 'Type de contenu')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Le type détermine la présentation du bloc sur le site et les champs réellement utilisés. Un type inconnu est ignoré : la page reste accessible.')
            ->setFormType(EnumType::class)
            ->setFormTypeOptions([
                'class' => SectionType::class,
                'choice_label' => static fn (SectionType $type): string => $type->label(),
            ]);
        yield IntegerField::new('position', 'Position')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('Ordre des blocs sur la page. Par exemple, 1 apparaît avant 2, puis 3.');
        yield BooleanField::new('isVisible', 'Visible')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('Masque ce bloc sur le site sans le supprimer. La page, elle, se publie séparément.');

        yield FormField::addFieldset('Texte', 'fa fa-align-left')->addCssClass('dgi-sec-group');
        yield TextField::new('eyebrow', 'Surtitre')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Courte ligne affichée au-dessus du titre. Laissez vide pour ne pas l’afficher.')
            ->hideOnIndex();
        yield TextField::new('title', 'Titre')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Titre du bloc. Pour une bannière, il sert seulement s’il n’y a pas encore de bannière active avec image.');
        yield $this->show(
            TextField::new('subtitle', 'Sous-titre')
                ->setColumns(FormColumns::MEDIUM)
                ->setHelp('Texte d’introduction affiché sous le titre. Laissez vide pour ne pas l’afficher.')
                ->hideOnIndex(),
            'subtitle',
        );
        yield $this->show(
            TextareaField::new('content', 'Contenu')
                ->setColumns(FormColumns::LARGE)
                ->setNumOfRows(8)
                ->setHelp('Texte principal du bloc. Laissez vide si ce type de contenu n’a pas besoin de paragraphe.')
                ->hideOnIndex(),
            'content',
        );

        yield FormField::addFieldset('Images', 'fa fa-image')
            ->addCssClass('dgi-sec-group')
            ->setHelp('Choisissez les images dans la '.$library.'.');
        yield $this->show(
            MediaAssociationField::new('image', 'Image principale')
                ->setColumns(FormColumns::MEDIUM)
                ->setHelp('Illustration du bloc. Pour une bannière, elle sert seulement tant qu’aucune bannière active n’a d’image.')
                ->hideOnIndex(),
            'image',
        );
        yield $this->show(
            MediaAssociationField::new('backgroundImage', 'Image de fond')
                ->setColumns(FormColumns::MEDIUM)
                ->setHelp('Image placée derrière le bloc, lorsque le type le prévoit. Laissez vide pour un fond uni.')
                ->hideOnIndex(),
            'backgroundImage',
        );

        yield FormField::addFieldset('Boutons', 'fa fa-hand-pointer')->addCssClass('dgi-sec-group');
        yield $this->show(
            TextField::new('buttonLabel', 'Texte du bouton')
                ->setColumns(FormColumns::MEDIUM)
                ->setHelp('Texte du bouton principal. Laissez vide pour ne pas afficher de bouton.')
                ->hideOnIndex(),
            'buttonLabel',
        );
        yield $this->show(
            TextField::new('buttonUrl', 'Lien du bouton')
                ->setColumns(FormColumns::MEDIUM)
                ->setHelp('Page ouverte lorsque le visiteur clique. Exemple pour une page du site : /nos-produits. Exemple externe : https://exemple.com.')
                ->hideOnIndex(),
            'buttonUrl',
        );
        yield $this->show(
            TextField::new('secondaryButtonLabel', 'Texte du second bouton')
                ->setColumns(FormColumns::MEDIUM)
                ->setHelp('Texte du second bouton. Il n’est utilisé que pour une bannière. Laissez vide pour ne pas l’afficher.')
                ->hideOnIndex(),
            'secondaryButtonLabel',
        );
        yield $this->show(
            TextField::new('secondaryButtonUrl', 'Lien du second bouton')
                ->setColumns(FormColumns::MEDIUM)
                ->setHelp('Page ouverte par le second bouton. Exemple : /contact.')
                ->hideOnIndex(),
            'secondaryButtonUrl',
        );

        yield FormField::addFieldset('Lignes du contenu', 'fa fa-list')->addCssClass('dgi-sec-group')
            ->setHelp('Utilisez les lignes pour une liste : activités, valeurs, distribution, galerie ou contenu libre.');
        yield $this->show(
            CollectionField::new('items', 'Lignes')
                ->setColumns(FormColumns::LARGE)
                ->setHelp('Chaque ligne peut avoir un titre, un texte et une image. L’image d’une ligne est surtout affichée dans une galerie.')
                ->setEntryType(SectionItemType::class)
                ->allowAdd()
                ->allowDelete()
                ->setEntryIsComplex()
                ->setFormTypeOption('by_reference', false)
                ->onlyOnForms(),
            'items',
        );

        yield FormField::addFieldset('Présentation', 'fa fa-paintbrush')->addCssClass('dgi-sec-group');
        yield $this->show(
            ChoiceField::new('theme', 'Fond')
                ->setColumns(FormColumns::MEDIUM)
                ->setFormType(EnumType::class)
                ->setFormTypeOptions([
                    'class' => SectionTheme::class,
                    'choice_label' => static fn (SectionTheme $theme): string => $theme->label(),
                ])
                ->setHelp('Fond clair, fond sombre, ou image placée à gauche pour un bloc Texte et image.')
                ->hideOnIndex(),
            'theme',
        );
    }

    private function show(object $field, string $name): object
    {
        $class = SectionFormVisibility::cssClass($name);
        if ($class !== '' && method_exists($field, 'addCssClass')) {
            $field->addCssClass($class);
        }

        return $field;
    }

    private function typeGuide(): string
    {
        $items = '';
        foreach (SectionType::cases() as $type) {
            $items .= '<li><strong>'
                .htmlspecialchars($type->label(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                .'</strong> — '
                .htmlspecialchars($type->guide(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                .'</li>';
        }

        return '<p>Le type décide de ce qui s’affiche. Les champs masqués ne sont pas effacés.</p><ul class="dgi-type-guide">'.$items.'</ul>';
    }
}
