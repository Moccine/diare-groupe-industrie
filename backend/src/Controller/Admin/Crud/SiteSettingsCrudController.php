<?php

namespace App\Controller\Admin\Crud;

use App\Admin\FormColumns;
use App\Entity\SiteSettings;
use App\Repository\SiteSettingsRepository;
use App\Service\AdminLinkFactory;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

final class SiteSettingsCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly SiteSettingsRepository $siteSettingsRepository,
        private readonly AdminLinkFactory $adminLinks,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return SiteSettings::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::DELETE, Action::BATCH_DELETE);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Coordonnées et réglages')
            ->setEntityLabelInPlural('Coordonnées et réglages')
            ->setHelp(Crud::PAGE_NEW, 'Ces informations alimentent l’en-tête, le pied de page, la carte et le référencement par défaut du site.')
            ->setHelp(Crud::PAGE_EDIT, 'Ces informations alimentent l’en-tête, le pied de page, la carte et le référencement par défaut du site.');
    }

    public function configureFields(string $pageName): iterable
    {
        $library = $this->adminLinks->anchor(MediaCrudController::class, 'bibliothèque d’images');
        yield FormField::addFieldset('Identité', 'fa fa-building')
            ->setHelp('Le logo et l’icône se choisissent dans la '.$library.'.');
        yield TextField::new('companyName', 'Nom de l’entreprise')
            ->setColumns(FormColumns::LARGE)
            ->setHelp('Nom affiché dans l’en-tête, le pied de page et les résultats de recherche.');
        yield AssociationField::new('logo', 'Logo')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Logo officiel, affiché dans l’en-tête, le pied de page et l’écran de chargement. Ajoutez-le d’abord dans la bibliothèque d’images.');
        yield AssociationField::new('favicon', 'Favicon')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Petite icône affichée dans l’onglet du navigateur.')
            ->hideOnIndex();

        yield FormField::addFieldset('Palette', 'fa fa-palette')
            ->addCssClass('dgi-palette')
            ->setHelp('Ces trois couleurs habillent le site. Le format est #RRGGBB, par exemple #185424.');
        yield ColorField::new('primaryColor', 'Vert principal')
            ->addCssClass('dgi-color dgi-color--primary')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('Couleur institutionnelle principale. La référence de la charte est #185424.')
            ->hideOnIndex();
        yield ColorField::new('secondaryColor', 'Vert sombre')
            ->addCssClass('dgi-color dgi-color--secondary')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('Couleur des grands aplats, comme l’en-tête et le pied de page. Référence : #0E3A18.')
            ->hideOnIndex();
        yield ColorField::new('accentColor', 'Lime')
            ->addCssClass('dgi-color dgi-color--accent')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('Couleur d’accent, pour les icônes et les éléments cliquables. Référence : #90B43C.')
            ->hideOnIndex();

        yield FormField::addFieldset('Contact', 'fa fa-address-card');
        yield EmailField::new('email', 'Email')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Adresse publique affichée dans le pied de page et sur la page contact. Laissez vide tant qu’aucune adresse n’est confirmée.')
            ->hideOnIndex();
        yield TelephoneField::new('phone', 'Téléphone')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Numéro affiché dans le pied de page et sur la page contact.');
        yield TextField::new('address', 'Adresse')
            ->setColumns(FormColumns::LARGE)
            ->setHelp('Adresse affichée dans le pied de page et sur la page contact.');
        yield FormField::addFieldset('Carte', 'fa fa-map')
            ->setHelp('La carte publique reste masquée tant que la latitude et la longitude ne sont pas renseignées. N’inventez pas de coordonnées.');
        yield TextField::new('mapLatitude', 'Latitude')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('Position nord-sud, entre -90 et 90, avec un point. Laissez vide si elle n’est pas confirmée.')
            ->setFormTypeOption('attr', ['inputmode' => 'decimal', 'placeholder' => '0.000000'])
            ->hideOnIndex();
        yield TextField::new('mapLongitude', 'Longitude')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('Position est-ouest, entre -180 et 180, avec un point. Laissez vide si elle n’est pas confirmée.')
            ->setFormTypeOption('attr', ['inputmode' => 'decimal', 'placeholder' => '0.000000'])
            ->hideOnIndex();
        yield IntegerField::new('mapZoom', 'Zoom')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('Entre 1 et 19. Laisser vide pour utiliser 15.')
            ->setFormTypeOption('attr', ['min' => 1, 'max' => 19, 'placeholder' => '15'])
            ->hideOnIndex();
        yield TextField::new('mapLabel', 'Libellé de la carte')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Affiché sur la carte. Si vide, le nom de l’entreprise est utilisé.')
            ->hideOnIndex();
        yield FormField::addFieldset('Réseaux', 'fa fa-share-nodes');
        yield UrlField::new('facebook', 'Facebook')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Adresse complète de la page Facebook. Laissez vide pour masquer le lien.')
            ->hideOnIndex();
        yield UrlField::new('linkedin', 'LinkedIn')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Adresse complète de la page LinkedIn. Laissez vide pour masquer le lien.')
            ->hideOnIndex();
        yield UrlField::new('instagram', 'Instagram')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Adresse complète du profil Instagram. Laissez vide pour masquer le lien.')
            ->hideOnIndex();
        yield UrlField::new('youtube', 'YouTube')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Adresse complète de la chaîne YouTube. Laissez vide pour masquer le lien.')
            ->hideOnIndex();
        yield FormField::addFieldset('En-tête et pied de page', 'fa fa-window-maximize');
        yield TextField::new('headerCtaLabel', 'Bouton d’en-tête')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Texte du bouton dans le menu, par exemple « Contact ».')
            ->hideOnIndex();
        yield TextField::new('headerCtaUrl', 'Lien du bouton')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Destination du bouton de menu. Une adresse interne s’écrit /contact.')
            ->hideOnIndex();
        yield TextareaField::new('footerText', 'Texte de pied de page')
            ->setColumns(FormColumns::LARGE)
            ->setNumOfRows(4)
            ->setHelp('Court texte de présentation affiché dans le pied de page.')
            ->hideOnIndex();
        yield TextField::new('copyright', 'Copyright')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Mention affichée après le nom et l’année, en bas du site.')
            ->hideOnIndex();
        yield FormField::addFieldset('Référencement Google', 'fa fa-magnifying-glass')
            ->setHelp('Ces champs choisissent le titre et le texte qui peuvent apparaître dans Google lorsque la page n’en a pas.');
        yield TextField::new('metaTitle', 'Titre Google par défaut')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Titre qui peut apparaître dans Google. Les pages qui ont leur propre titre l’utilisent à la place.')
            ->hideOnIndex();
        yield TextareaField::new('metaDescription', 'Description Google par défaut')
            ->setColumns(FormColumns::LARGE)
            ->setNumOfRows(4)
            ->setHelp('Texte qui peut apparaître sous le titre dans Google. Les pages qui ont le leur l’utilisent à la place.')
            ->hideOnIndex();
    }

    public function persistEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        if ($this->siteSettingsRepository->findSingleton() instanceof SiteSettings) {
            throw new \RuntimeException('Les paramètres du site existent déjà. Modifiez la fiche existante.');
        }

        parent::persistEntity($entityManager, $entityInstance);
    }
}
