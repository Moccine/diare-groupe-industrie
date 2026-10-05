<?php

namespace App\Controller\Admin\Crud;

use App\Admin\FormColumns;
use App\Entity\Media;
use App\Repository\MediaRepository;
use App\Service\MediaUploader;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\File;

final class MediaCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly MediaUploader $mediaUploader,
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
        yield ImageField::new('fileName', 'Aperçu')
            ->setBasePath('uploads/media')
            ->onlyOnIndex();

        yield FormField::addFieldset('Fichier', 'fa fa-file-image');
        yield Field::new('uploadFile', 'Fichier')
            ->setColumns(FormColumns::LARGE)
            ->setFormType(FileType::class)
            ->setFormTypeOptions([
                'mapped' => false,
                'required' => $pageName === Crud::PAGE_NEW,
                'constraints' => [
                    new File(
                        maxSize: '8M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'],
                        mimeTypesMessage: 'Formats acceptés : JPEG, PNG, WebP, GIF, AVIF.',
                    ),
                ],
            ])
            ->setHelp($pageName === Crud::PAGE_NEW
                ? 'Fichier image à enregistrer. Il est converti en WebP lorsque c’est possible.'
                : 'Laissez vide pour conserver le fichier actuel. Un nouveau fichier remplace l’ancien.')
            ->onlyOnForms();

        yield FormField::addFieldset('Description', 'fa fa-align-left');
        yield TextField::new('title', 'Titre')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Nom pour retrouver l’image dans les listes de l’administration.');
        yield TextField::new('alt', 'Texte alternatif')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Décrit l’image pour les personnes utilisant un lecteur d’écran et aide aussi les moteurs de recherche. Exemple : « Ligne de production de lait Diaré Groupe Industrie ».');
        yield TextareaField::new('caption', 'Légende')
            ->setColumns(FormColumns::LARGE)
            ->setNumOfRows(4)
            ->setHelp('Légende affichée sous l’image dans un bloc Texte et image. Laissez vide si elle n’est pas utile.')
            ->hideOnIndex();

        yield FormField::addFieldset('Informations', 'fa fa-circle-info');
        yield TextField::new('originalName', 'Nom d’origine')
            ->setColumns(FormColumns::MEDIUM)
            ->setHelp('Nom du fichier tel qu’il était sur votre ordinateur.')
            ->onlyOnDetail();
        yield TextField::new('mimeType', 'Format')
            ->setColumns(FormColumns::SMALL)
            ->hideOnForm()
            ->hideOnIndex();
        yield TextField::new('formattedSize', 'Poids')
            ->setColumns(FormColumns::SMALL)
            ->formatValue(static fn ($value, Media $media) => $media->getFormattedSize())
            ->setHelp('Poids du fichier enregistré.')
            ->hideOnForm();

        if (in_array($pageName, [Crud::PAGE_EDIT, Crud::PAGE_DETAIL], true)) {
            yield FormField::addFieldset('Utilisation', 'fa fa-link')
                ->setHelp($this->usageNote());
        }
    }

    public function persistEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        if ($entityInstance instanceof Media) {
            $this->applyUpload($entityInstance, true);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        if ($entityInstance instanceof Media) {
            $this->applyUpload($entityInstance, false);
        }

        parent::updateEntity($entityManager, $entityInstance);
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

    private function applyUpload(Media $media, bool $required): void
    {
        $file = $this->uploadedFile();
        if (!$file instanceof UploadedFile) {
            if ($required) {
                throw new \RuntimeException('Veuillez sélectionner un fichier.');
            }

            return;
        }

        $previous = $media->getFileName();
        $stored = $this->mediaUploader->upload($file, $media->getAlt(), $media->getTitle(), $media->getCaption());
        $media
            ->setFileName($stored->getFileName())
            ->setOriginalName($stored->getOriginalName())
            ->setMimeType($stored->getMimeType())
            ->setSize($stored->getSize())
            ->setWidth($stored->getWidth())
            ->setHeight($stored->getHeight());

        if (trim($media->getAlt()) === '') {
            $media->setAlt($stored->getAlt());
        }

        if ($previous !== '' && $previous !== $media->getFileName()) {
            $this->mediaUploader->deleteStoredFile($previous);
        }
    }

    private function uploadedFile(): ?UploadedFile
    {
        $bag = $this->getContext()?->getRequest()->files->get('Media');
        if (!is_array($bag)) {
            return null;
        }

        $file = $bag['uploadFile'] ?? null;

        return $file instanceof UploadedFile ? $file : null;
    }
}
