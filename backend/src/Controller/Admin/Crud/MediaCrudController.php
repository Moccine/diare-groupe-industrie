<?php

namespace App\Controller\Admin\Crud;

use App\Admin\FormColumns;
use App\Entity\Media;
use App\Repository\MediaRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Validator\Constraints\File;
use Vich\UploaderBundle\Form\Type\VichImageType;

final class MediaCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly MediaRepository $mediaRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Media::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Image')
            ->setEntityLabelInPlural('Bibliothèque d’images')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['title', 'alt', 'originalName'])
            ->setHelp(Crud::PAGE_NEW, 'La bibliothèque contient les images réutilisables dans les produits, actualités, pages et réglages du site. Formats acceptés : JPEG, PNG, WebP, GIF ou AVIF. Poids maximum : 8 Mo.')
            ->setHelp(Crud::PAGE_EDIT, 'Cette image peut être réutilisée dans les produits, actualités, pages et réglages du site.')
            ->setHelp(Crud::PAGE_DETAIL, 'Cette image peut être réutilisée dans les produits, actualités, pages et réglages du site.');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('thumbnailPath', 'Miniature')
            ->setTemplatePath('admin/field/media_thumb.html.twig')
            ->setSortable(false)
            ->hideOnForm();
        yield TextField::new('title', 'Titre')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Nom pour retrouver l’image dans les listes de l’administration.');
        yield TextField::new('originalName', 'Nom d’origine')
            ->setHelp('Nom du fichier tel qu’il était sur votre ordinateur.')
            ->hideOnForm();
        yield TextField::new('dimensionsLabel', 'Dimensions')
            ->setSortable(false)
            ->hideOnForm();
        yield TextField::new('formattedSize', 'Poids')
            ->formatValue(static fn ($value, Media $media) => $media->getFormattedSize())
            ->setSortable(false)
            ->hideOnForm();
        yield DateTimeField::new('createdAt', 'Date')
            ->onlyOnIndex();

        $current = $this->getContext()?->getEntity()?->getInstance();
        $currentHelp = 'Fichier image à enregistrer. Il est converti en WebP lorsque c’est possible. Poids maximum : 8 Mo.';
        if ($pageName !== Crud::PAGE_NEW && $current instanceof Media && $current->getFileName() !== '') {
            $currentHelp = sprintf(
                'Image actuelle : %s — %s — %s — %s. Laissez le fichier vide pour la conserver, ou choisissez-en un autre pour la remplacer.',
                $current->getOriginalName() !== '' ? $current->getOriginalName() : $current->getFileName(),
                $current->getDimensionsLabel(),
                $current->getFormattedSize(),
                $current->getMimeType() !== '' ? $current->getMimeType() : 'type inconnu',
            );
        }

        yield FormField::addFieldset('Fichier', 'fa fa-file-image')->onlyOnForms();
        yield Field::new('imageFile', 'Fichier')
            ->setColumns(FormColumns::LARGE)
            ->setFormType(VichImageType::class)
            ->setFormTypeOptions([
                'required' => $pageName === Crud::PAGE_NEW,
                'allow_delete' => false,
                'download_uri' => false,
                'image_uri' => static function (Media $media, ?string $uri = null): ?string {
                    return $media->getThumbnailPath() !== '' ? $media->getThumbnailPath() : $uri;
                },
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp,image/gif,image/avif',
                    'data-dgi-upload-preview' => '1',
                ],
                'constraints' => [
                    new File(
                        maxSize: '8M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'],
                        mimeTypesMessage: 'Formats acceptés : JPEG, PNG, WebP, GIF, AVIF.',
                    ),
                ],
            ])
            ->setHelp($currentHelp)
            ->onlyOnForms();

        yield FormField::addFieldset('Description', 'fa fa-align-left')->onlyOnForms();
        yield TextField::new('alt', 'Texte alternatif')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Décrit l’image pour les personnes utilisant un lecteur d’écran et aide aussi les moteurs de recherche. Exemple : « Ligne de production de lait Diaré Groupe Industrie ».')
            ->hideOnIndex();
        yield TextareaField::new('caption', 'Légende')
            ->setColumns(FormColumns::LARGE)
            ->setNumOfRows(4)
            ->setHelp('Légende affichée sous l’image dans un bloc Texte et image. Laissez vide si elle n’est pas utile.')
            ->hideOnIndex();

        yield TextField::new('mimeType', 'Format')
            ->onlyOnDetail();

        if (in_array($pageName, [Crud::PAGE_EDIT, Crud::PAGE_DETAIL], true)) {
            yield FormField::addFieldset('Utilisation', 'fa fa-link')
                ->setHelp($this->usageNote());
        }
    }

    private function usageNote(): string
    {
        $media = $this->getContext()?->getEntity()?->getInstance();
        if (!$media instanceof Media || $media->getId() === null) {
            return 'Cette image n’est pas encore utilisée.';
        }

        $labels = $this->mediaRepository->usageLabels($media);
        if ($labels === []) {
            return 'Cette image n’est pas encore utilisée. Vous pourrez la choisir dans un produit, une actualité, une page ou les réglages.';
        }

        $extra = 0;
        if (count($labels) > 12) {
            $extra = count($labels) - 12;
            $labels = array_slice($labels, 0, 12);
        }

        $items = '';
        foreach ($labels as $label) {
            $items .= '<li>'.htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</li>';
        }
        $more = $extra > 0 ? '<p>Et '.$extra.' autre'.($extra > 1 ? 's' : '').' utilisation'.($extra > 1 ? 's' : '').'.</p>' : '';

        return '<p>Utilisée par :</p><ul class="dgi-usage">'.$items.'</ul>'.$more.'<p>Si vous la supprimez, ces emplacements n’afficheront plus cette image.</p>';
    }
}
