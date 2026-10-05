<?php

namespace App\Form\Extension;

use App\Controller\Admin\Crud\MediaCrudController;
use App\Controller\Admin\DashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\CrudAutocompleteType;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CrudAutocompleteChoiceAttrExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [CrudAutocompleteType::class, EntityType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        if ($resolver->isDefined('choice_attr')) {
            return;
        }

        $resolver->setDefined('choice_attr');
        $resolver->setAllowedTypes('choice_attr', ['null', 'array', 'callable', \Closure::class]);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $select = $this->mediaPickerView($view);
        if (!$select instanceof FormView || isset($select->vars['attr']['data-dgi-media-edit-template'])) {
            return;
        }

        $select->vars['attr']['data-dgi-media-edit-template'] = $this->adminUrlGenerator
            ->unsetAll()
            ->setDashboard(DashboardController::class)
            ->setController(MediaCrudController::class)
            ->setAction(Action::EDIT)
            ->setEntityId('__ID__')
            ->generateUrl();
    }

    private function mediaPickerView(FormView $view): ?FormView
    {
        if (isset($view['autocomplete']) && $this->isMediaPicker($view['autocomplete'])) {
            return $view['autocomplete'];
        }

        if ($this->isMediaPicker($view)) {
            return $view;
        }

        return null;
    }

    private function isMediaPicker(FormView $view): bool
    {
        return ($view->vars['attr']['data-dgi-media-picker'] ?? null) === '1';
    }
}
