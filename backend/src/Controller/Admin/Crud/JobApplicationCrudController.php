<?php

namespace App\Controller\Admin\Crud;

use App\Admin\FormColumns;
use App\Entity\JobApplication;
use App\Enum\JobApplicationStatus;
use App\Service\JobApplicationCvStorage;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\Form\Extension\Core\Type\EnumType;

final class JobApplicationCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly JobApplicationCvStorage $cvStorage,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return JobApplication::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $this->cvAction('viewCvList', 'Consulter le CV', 'admin_job_application_cv_view', true))
            ->add(Crud::PAGE_DETAIL, $this->cvAction('viewCv', 'Consulter le CV', 'admin_job_application_cv_view', true))
            ->add(Crud::PAGE_DETAIL, $this->cvAction('downloadCv', 'Télécharger le CV', 'admin_job_application_cv_download', false));
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Candidature')
            ->setEntityLabelInPlural('Candidatures')
            ->setPageTitle(Crud::PAGE_INDEX, 'Candidatures')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setDefaultRowAction(Action::DETAIL)
            ->setSearchFields(['lastName', 'firstName', 'email', 'phone', 'jobTitleSnapshot', 'desiredRole'])
            ->setHelp(Crud::PAGE_INDEX, 'Dossiers reçus depuis le formulaire du site. Les CV ne sont pas publics.')
            ->setHelp(Crud::PAGE_DETAIL, 'Le CV se consulte ici, après connexion. Il n’est pas envoyé au candidat ni joint aux e-mails.')
            ->setHelp(Crud::PAGE_EDIT, 'Le changement de statut n’envoie aucun e-mail. Les notes internes ne sont jamais transmises au candidat.');
    }

    public function configureFilters(Filters $filters): Filters
    {
        $statuses = [];
        foreach (JobApplicationStatus::cases() as $status) {
            $statuses[$status->label()] = $status;
        }

        return $filters
            ->add(ChoiceFilter::new('status', 'Statut')->setChoices($statuses))
            ->add(EntityFilter::new('jobOffer', 'Offre'))
            ->add(BooleanFilter::new('isSpontaneous', 'Candidature spontanée'))
            ->add(DateTimeFilter::new('createdAt', 'Date de candidature'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield DateTimeField::new('createdAt', 'Reçue le')
            ->hideOnForm();
        yield TextField::new('fullName', 'Candidat')
            ->setSortable(false)
            ->onlyOnIndex();
        yield TextField::new('positionLabel', 'Poste')
            ->setSortable(false)
            ->onlyOnIndex();
        yield EmailField::new('email', 'E-mail')
            ->hideOnForm();
        yield TelephoneField::new('phone', 'Téléphone')
            ->hideOnForm();
        yield TextField::new('statusLabel', 'Statut')
            ->hideOnForm();
        yield BooleanField::new('cvAvailable', 'CV')
            ->renderAsSwitch(false)
            ->onlyOnIndex();

        yield FormField::addFieldset('Candidat', 'fa fa-user')->hideOnIndex();
        yield TextField::new('firstName', 'Prénom')
            ->setColumns(FormColumns::MEDIUM)
            ->hideOnIndex()
            ->hideOnForm();
        yield TextField::new('lastName', 'Nom')
            ->setColumns(FormColumns::MEDIUM)
            ->hideOnIndex()
            ->hideOnForm();
        yield UrlField::new('linkedinUrl', 'LinkedIn')
            ->setColumns(FormColumns::MEDIUM)
            ->hideOnIndex()
            ->hideOnForm();
        yield TextField::new('availability', 'Disponibilité')
            ->setColumns(FormColumns::MEDIUM)
            ->hideOnIndex()
            ->hideOnForm();

        yield FormField::addFieldset('Poste', 'fa fa-briefcase')->hideOnIndex();
        yield AssociationField::new('jobOffer', 'Offre')
            ->setColumns(FormColumns::MEDIUM)
            ->hideOnIndex()
            ->hideOnForm();
        yield TextField::new('jobTitleSnapshot', 'Intitulé au moment de la candidature')
            ->setColumns(FormColumns::MEDIUM)
            ->hideOnIndex()
            ->hideOnForm();
        yield TextField::new('desiredRole', 'Domaine recherché')
            ->setColumns(FormColumns::MEDIUM)
            ->hideOnIndex()
            ->hideOnForm();
        yield TextField::new('contractTypeSnapshot', 'Contrat')
            ->setColumns(FormColumns::MEDIUM)
            ->hideOnIndex()
            ->hideOnForm();
        yield TextField::new('locationSnapshot', 'Lieu')
            ->setColumns(FormColumns::MEDIUM)
            ->hideOnIndex()
            ->hideOnForm();
        yield BooleanField::new('isSpontaneous', 'Candidature spontanée')
            ->renderAsSwitch(false)
            ->hideOnIndex()
            ->hideOnForm();

        yield FormField::addFieldset('Motivation', 'fa fa-align-left')->hideOnIndex();
        yield TextareaField::new('motivation', 'Motivation')
            ->setColumns(FormColumns::LARGE)
            ->setNumOfRows(10)
            ->hideOnIndex()
            ->hideOnForm();
        yield TextField::new('cvOriginalFilename', 'Fichier CV')
            ->hideOnIndex()
            ->hideOnForm();
        yield TextField::new('cvSizeLabel', 'Taille du CV')
            ->hideOnIndex()
            ->hideOnForm();

        yield FormField::addFieldset('Suivi RH', 'fa fa-clipboard-check');
        yield ChoiceField::new('status', 'Statut')
            ->setColumns(FormColumns::MEDIUM)
            ->setFormType(EnumType::class)
            ->setFormTypeOptions([
                'class' => JobApplicationStatus::class,
                'choice_label' => static fn (JobApplicationStatus $status): string => $status->label(),
            ])
            ->setHelp('Visible uniquement dans l’administration. Aucun e-mail n’est envoyé.')
            ->onlyOnForms();
        yield TextareaField::new('internalNotes', 'Notes internes')
            ->setColumns(FormColumns::LARGE)
            ->setNumOfRows(6)
            ->setHelp('Réservées à l’équipe. Elles ne sont jamais envoyées au candidat.')
            ->hideOnIndex();
        yield DateTimeField::new('processedAt', 'Traitée le')
            ->setColumns(FormColumns::MEDIUM)
            ->hideOnIndex()
            ->hideOnForm();
        yield DateTimeField::new('consentedAt', 'Consentement le')
            ->hideOnIndex()
            ->hideOnForm();
        yield TextField::new('consentVersion', 'Version du consentement')
            ->hideOnIndex()
            ->hideOnForm();
        yield DateTimeField::new('retentionUntil', 'Fin de conservation')
            ->setHelp('Vide tant qu’aucune durée n’est configurée. La purge reste une commande manuelle.')
            ->hideOnIndex()
            ->hideOnForm();
    }

    public function deleteEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        $filename = $entityInstance instanceof JobApplication ? $entityInstance->getCvStoredFilename() : '';
        parent::deleteEntity($entityManager, $entityInstance);
        if ($filename !== '') {
            $this->cvStorage->delete($filename);
        }
    }

    private function cvAction(string $name, string $label, string $route, bool $newTab): Action
    {
        $action = Action::new($name, $label, 'fa fa-file-pdf')
            ->linkToRoute($route, static fn (JobApplication $application): array => ['id' => $application->getId()])
            ->displayIf(static fn (JobApplication $application): bool => $application->isCvAvailable());

        if ($newTab) {
            $action->setHtmlAttributes(['target' => '_blank', 'rel' => 'noopener']);
        }

        return $action;
    }
}
