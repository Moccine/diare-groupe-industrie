<?php

namespace App\Controller\Admin\Crud;

use App\Admin\FormColumns;
use App\Admin\MediaAssociationField;
use App\Admin\PublicSiteAction;
use App\Entity\News;
use App\Service\AdminLinkFactory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class NewsCrudController extends AbstractCrudController
{
    private const SLUG_UNLOCK = 'Modifier cette adresse change le lien de l’actualité. Les adresses partagées ne fonctionneront plus. Voulez-vous continuer ?';

    public function __construct(
        private readonly AdminLinkFactory $adminLinks,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return News::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return PublicSiteAction::add($actions, $this->publicUrl(...));
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Actualité')
            ->setEntityLabelInPlural('Actualités')
            ->setDefaultSort(['publishedAt' => 'DESC'])
            ->setSearchFields(['title', 'slug', 'excerpt'])
            ->setHelp(Crud::PAGE_NEW, 'Une actualité devient visible lorsqu’elle est publiée et que sa date de publication est atteinte. Une date future permet de la préparer à l’avance.')
            ->setHelp(Crud::PAGE_EDIT, 'Une actualité devient visible lorsqu’elle est publiée et que sa date de publication est atteinte. Une date future permet de la préparer à l’avance.');
    }

    public function configureFields(string $pageName): iterable
    {
        $library = $this->adminLinks->anchor(MediaCrudController::class, 'bibliothèque d’images');

        yield MediaAssociationField::index('image');
        yield FormField::addFieldset('Article', 'fa fa-newspaper')
            ->setHelp('L’image se choisit dans la '.$library.'.');
        yield TextField::new('title', 'Titre')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Titre de l’actualité, affiché dans la liste et en haut de l’article.');
        yield MediaAssociationField::new('image', 'Image')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Illustration de l’article. Ajoutez-la d’abord dans la bibliothèque d’images.')
            ->hideOnIndex();
        yield TextareaField::new('excerpt', 'Chapô')
            ->setColumns(FormColumns::LARGE)
            ->setNumOfRows(4)
            ->setHelp('Résumé affiché dans la liste des actualités, avant d’ouvrir l’article.')
            ->hideOnIndex();
        yield TextareaField::new('content', 'Contenu')
            ->setColumns(FormColumns::LARGE)
            ->setNumOfRows(8)
            ->setHelp('Texte complet de l’article.')
            ->hideOnIndex();

        yield FormField::addFieldset('Publication', 'fa fa-calendar');
        yield DateTimeField::new('publishedAt', 'Date de publication')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Date affichée sur l’article. Une date future laisse l’actualité enregistrée, mais invisible jusqu’à ce jour.');
        yield BooleanField::new('isPublished', 'Publiée')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('L’actualité devient visible uniquement lorsqu’elle est publiée et lorsque sa date de publication est atteinte.');

        yield FormField::addFieldset('Adresse', 'fa fa-link')
            ->setHelp('Fin du lien de l’article. Elle se remplit à partir du titre. Ne la changez pas si l’article a déjà été partagé.');
        yield SlugField::new('slug', 'Adresse URL')
            ->setTargetFieldName('title')
            ->setUnlockConfirmationMessage(self::SLUG_UNLOCK)
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Partie visible du lien, par exemple nouvelle-collecte. Évitez de la changer après avoir partagé l’article.')
            ->hideOnIndex();

        yield FormField::addFieldset('Référencement Google', 'fa fa-magnifying-glass')
            ->setHelp('Ces champs choisissent le titre et le texte qui peuvent apparaître dans Google. S’ils sont vides, le titre et le chapô sont utilisés.');
        yield TextField::new('metaTitle', 'Titre Google')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Titre qui peut apparaître dans Google. S’il est vide, le titre de l’article est utilisé.')
            ->hideOnIndex();
        yield TextareaField::new('metaDescription', 'Description Google')
            ->setColumns(FormColumns::LARGE)
            ->setNumOfRows(4)
            ->setHelp('Texte qui peut apparaître sous le titre dans Google. S’il est vide, le chapô est utilisé.')
            ->hideOnIndex();
    }

    private function publicUrl(News $news): ?string
    {
        if (!$news->isPublished() || $news->getSlug() === '' || $news->getPublishedAt() > new \DateTimeImmutable()) {
            return null;
        }

        return $this->urlGenerator->generate('news_show', ['slug' => $news->getSlug()]);
    }
}
