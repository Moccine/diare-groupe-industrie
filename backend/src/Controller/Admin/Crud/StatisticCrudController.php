<?php

namespace App\Controller\Admin\Crud;

use App\Admin\FormColumns;
use App\Entity\Statistic;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class StatisticCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Statistic::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Chiffre clé')
            ->setEntityLabelInPlural('Chiffres clés')
            ->setDefaultSort(['position' => 'ASC'])
            ->setHelp(Crud::PAGE_INDEX, 'N’indiquez un nombre que s’il est confirmé. Sinon laissez « À renseigner ». Exemple : valeur 25, libellé « années d’expérience ».')
            ->setHelp(Crud::PAGE_NEW, 'N’indiquez un nombre que s’il est confirmé. Sinon laissez « À renseigner ». Exemple : valeur 25, libellé « années d’expérience ».')
            ->setHelp(Crud::PAGE_EDIT, 'N’indiquez un nombre que s’il est confirmé. Sinon laissez « À renseigner ». Exemple : valeur 25, libellé « années d’expérience ».');
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Chiffre', 'fa fa-chart-simple')
            ->setHelp('Ces chiffres apparaissent dans les blocs Chiffres clés des pages. N’écrivez un nombre que s’il est confirmé.');
        yield TextField::new('value', 'Valeur')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('Nombre affiché en grand. Pour voir 25+ sur le site, écrivez 25+. Sinon laissez « À renseigner ».');
        yield TextField::new('label', 'Libellé')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Texte affiché sous le nombre. Exemple : Années d’expérience.');
        yield TextareaField::new('description', 'Précision')
            ->setColumns(FormColumns::LARGE)
            ->setNumOfRows(3)
            ->setHelp('Phrase courte sous le libellé. Elle est limitée à deux lignes à l’écran. Laissez vide si le libellé suffit.')
            ->hideOnIndex();

        yield FormField::addFieldset('Affichage', 'fa fa-eye');
        yield IntegerField::new('position', 'Position')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('Ordre dans le bandeau. Par exemple, 1 apparaît avant 2, puis 3.');
        yield BooleanField::new('isVisible', 'Publié')
            ->setColumns(FormColumns::SMALL)
            ->setHelp('Seuls les chiffres publiés apparaissent sur le site.');
    }
}
