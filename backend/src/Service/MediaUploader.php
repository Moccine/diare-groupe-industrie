<?php

namespace App\Service;

use App\Entity\Media;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

final class MediaUploader
{
    public function __construct(
        private readonly MediaImageProcessor $processor,
    ) {
    }

    public function upload(UploadedFile|string $source, string $alt, ?string $title = null, ?string $caption = null): Media
    {
        $path = $source instanceof UploadedFile ? $source->getPathname() : $source;
        $originalName = $source instanceof UploadedFile
            ? $source->getClientOriginalName()
            : basename($source);

        if (!is_file($path)) {
            throw new \RuntimeException('Le fichier image est introuvable.');
        }

        $safeOriginal = $this->processor->safeOriginalName($originalName);
        $media = new Media();
        $media->rememberOriginalName($originalName);
        $media->setImageFile(new ReplacingFile($path, true, false, false));
        $media->setAlt(trim($alt) !== '' ? trim($alt) : pathinfo($safeOriginal, PATHINFO_FILENAME));
        $media->setTitle($title);
        $media->setCaption($caption);

        return $media;
    }
}
