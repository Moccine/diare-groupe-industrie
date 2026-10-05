<?php

namespace App\Controller\Admin\Crud;

use App\Admin\FormColumns;
use App\Admin\PublicSiteAction;
use App\Entity\Page;
use App\Service\AdminLinkFactory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PageCrudController extends AbstractCrudController
{
    private const SLUG_UNLOCK = 'Modifier cette adresse change le lien de la page. Les adresses partagées ne fonctionneront plus. Voulez-vous continuer ?';

    public function __construct(
        private readonly AdminLinkFactory $adminLinks,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Page::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return PublicSiteAction::add($actions, $this->publicUrl(...));
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Page du site')
            ->setEntityLabelInPlural('Pages du site')
            ->setDefaultSort(['menuPosition' => 'ASC'])
            ->setSearchFields(['title', 'slug', 'menuTitle'])
            ->setHelp(Crud::PAGE_NEW, 'Une page représente une page du site. Son contenu est composé ensuite de blocs, dans « Contenus des pages ».')
            ->setHelp(Crud::PAGE_EDIT, 'Une page représente une page du site. Son contenu est composé de blocs, dans « Contenus des pages ».');
    }

    public function configureFields(string $pageName): iterable
    {
        $page = $this->getContext()?->getEntity()?->getInstance();
        $parameters = [];
        if ($page instanceof Page && $page->getId() !== null) {
            $parameters = [
                'filters[page][comparison]' => '=',
                'filters[page][value]' => (string) $page->getId(),
            ];
        }
        $sections = $this->adminLinks->anchor(SectionCrudController::class, 'Contenus des pages', Action::INDEX, null, $parameters);

        yield FormField::addFieldset('Informations de la page', 'fa fa-file-lines')
            ->setHelp('Après avoir créé la page, ajoutez ses blocs dans '.$sections.'.');
        yield TextField::new('title', 'Titre')
            ->setColumns(FormColumns::LARGE)
            ->setHelp('Titre principal affiché en haut de la page.');

        yield FormField::addFieldset('Navigation', 'fa fa-bars');
        yield TextField::new('menuTitle', 'Titre dans le menu')
            ->setColumns(FormColumns::HALF_ON_DESKTOP)
            ->setHelp('Libellé affiché dans le menu. S’il est vide, le titre de la page est utilisé.')
            ->hideOnIndex();
        yield BooleanField::new('showInMenu', 'Dans le menu')
            ->setColumns(FormColumns::QUARTER)
            ->setHelp('Ajoute cette page au menu principal du site.');
        yield IntegerField::new('menuPosition', 'Position dans le menu')
            ->setColumns(FormColumns::QUARTER)
            ->setHelp('Ordre dans le menu. Par exemple, 1 apparaît avant 2, puis 3.');

        yield FormField::addFieldset('Publication', 'fa fa-eye');
        yield BooleanField::new('isPublished', 'Publiée')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('Une page non publiée n’est plus accessible aux visiteurs. Elle reste enregistrée ici.');

        yield FormField::addFieldset('Adresse', 'fa fa-link')
            ->setHelp('L’adresse est la fin du lien, par exemple nos-produits. La page d’accueil utilise « accueil ».');
        yield SlugField::new('slug', 'Adresse URL')
            ->setTargetFieldName('title')
            ->setUnlockConfirmationMessage(self::SLUG_UNLOCK)
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Partie visible du lien, par exemple nos-produits. Évitez de la changer après avoir partagé la page.');

        yield FormField::addFieldset('Référencement Google', 'fa fa-magnifying-glass')
            ->setHelp('Ces champs choisissent le titre et le texte qui peuvent apparaître dans Google. S’ils sont vides, le site utilise les informations de la page.');
        yield TextField::new('metaTitle', 'Titre Google')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Titre qui peut apparaître dans Google. S’il est vide, le titre de la page est utilisé.')
            ->hideOnIndex();
        yield TextareaField::new('metaDescription', 'Description Google')
            ->setColumns(FormColumns::LARGE)
            ->setNumOfRows(4)
            ->setHelp('Texte qui peut apparaître sous le titre dans Google. S’il est vide, le texte par défaut du site est utilisé.')
            ->hideOnIndex();
    }

    private function publicUrl(Page $page): ?string
    {
        if (!$page->isPublished() || $page->getSlug() === '') {
            return null;
        }

        if ($page->getSlug() === 'accueil') {
            return $this->urlGenerator->generate('home');
        }

        return $this->urlGenerator->generate('page_show', ['slug' => $page->getSlug()]);
    }
}
