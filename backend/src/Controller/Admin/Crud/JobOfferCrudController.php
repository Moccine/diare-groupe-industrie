<?php

namespace App\Controller\Admin\Crud;

use App\Admin\FormColumns;
use App\Admin\PublicSiteAction;
use App\Entity\JobOffer;
use App\Enum\ContractType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class JobOfferCrudController extends AbstractCrudController
{
    private const SLUG_UNLOCK = 'Modifier cette adresse change le lien de l’offre. Les adresses partagées ne fonctionneront plus. Voulez-vous continuer ?';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return JobOffer::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return PublicSiteAction::add($actions, $this->publicUrl(...));
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Offre d’emploi')
            ->setEntityLabelInPlural('Offres d’emploi')
            ->setDefaultSort(['publishedAt' => 'DESC'])
            ->setSearchFields(['title', 'location', 'department'])
            ->setHelp(Crud::PAGE_NEW, 'L’offre est visible sur la page recrutement tant qu’elle est publiée, que sa date de publication est atteinte et qu’elle n’est pas expirée.')
            ->setHelp(Crud::PAGE_EDIT, 'L’offre est visible sur la page recrutement tant qu’elle est publiée, que sa date de publication est atteinte et qu’elle n’est pas expirée.');
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Poste', 'fa fa-briefcase');
        yield TextField::new('title', 'Intitulé du poste')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Nom du poste, affiché sur la carte et la fiche.');
        yield TextField::new('location', 'Lieu')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Lieu de travail affiché sur l’offre.');
        yield ChoiceField::new('contractType', 'Contrat')
            ->setColumns(FormColumns::SMALL)
            ->setFormType(EnumType::class)
            ->setFormTypeOptions([
                'class' => ContractType::class,
                'choice_label' => static fn (ContractType $type): string => $type->label(),
            ])
            ->setHelp('Type de contrat affiché sur l’offre.');
        yield TextField::new('department', 'Service')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Service ou direction concernée. Laissez vide si ce n’est pas utile.')
            ->hideOnIndex();
        yield TextField::new('experienceLevel', 'Expérience')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Niveau d’expérience affiché sur l’offre. Ne précisez que ce qui est réellement demandé.')
            ->hideOnIndex();
        yield TextField::new('salaryLabel', 'Rémunération')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Mention affichée sur l’offre. Laissez vide si la rémunération n’est pas communiquée.')
            ->hideOnIndex();

        yield FormField::addFieldset('Présentation de l’offre', 'fa fa-align-left');
        yield TextareaField::new('shortDescription', 'Accroche')
            ->setColumns(FormColumns::LARGE)
            ->setNumOfRows(4)
            ->setHelp('Résumé affiché sur la carte de l’offre, avant d’ouvrir la fiche.')
            ->hideOnIndex();
        yield TextareaField::new('description', 'Description')
            ->setColumns(FormColumns::LARGE)
            ->setNumOfRows(8)
            ->setHelp('Missions et contexte du poste, visibles sur la fiche.')
            ->hideOnIndex();
        yield TextareaField::new('requirements', 'Profil recherché')
            ->setColumns(FormColumns::LARGE)
            ->setNumOfRows(6)
            ->setHelp('Compétences ou conditions affichées sur la fiche.')
            ->hideOnIndex();

        yield FormField::addFieldset('Candidature', 'fa fa-paper-plane')
            ->setHelp('Si un lien de candidature est renseigné, le candidat est envoyé vers ce lien. Sinon, l’adresse email est utilisée.');
        yield EmailField::new('applicationEmail', 'Email de candidature')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Utilisé seulement si aucun lien de candidature n’est renseigné. N’inventez pas d’adresse.')
            ->hideOnIndex();
        yield UrlField::new('applicationUrl', 'Lien de candidature')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Si ce lien est rempli, le candidat y est envoyé. Sinon, le bouton utilise l’email. Exemple : https://exemple.com/candidature.')
            ->hideOnIndex();

        yield FormField::addFieldset('Publication', 'fa fa-calendar');
        yield DateTimeField::new('publishedAt', 'Date de publication')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Date à partir de laquelle l’offre peut être visible. Une date future la masque jusque-là.')
            ->hideOnIndex();
        yield DateTimeField::new('expiresAt', 'Expiration')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Après cette date, l’offre n’est plus affichée sur le site, même si elle reste marquée publiée.');
        yield BooleanField::new('isPublished', 'Publiée')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('Seules les offres publiées, dont la date est atteinte et qui ne sont pas expirées, apparaissent sur le site.');

        yield FormField::addFieldset('Adresse', 'fa fa-link')
            ->setHelp('Fin du lien de la fiche. Elle se remplit à partir de l’intitulé du poste.');
        yield SlugField::new('slug', 'Adresse URL')
            ->setTargetFieldName('title')
            ->setUnlockConfirmationMessage(self::SLUG_UNLOCK)
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Partie visible du lien de l’offre. Ne la changez pas après avoir partagé l’offre.')
            ->hideOnIndex();
    }

    private function publicUrl(JobOffer $offer): ?string
    {
        if ($offer->getSlug() === '' || !$offer->isOpenAt(new \DateTimeImmutable())) {
            return null;
        }

        return $this->urlGenerator->generate('job_show', ['slug' => $offer->getSlug()]);
    }
}
