<?php

namespace App\EventListener;

use App\Entity\Media;
use App\Service\MediaImageProcessor;
use App\Service\MediaThumbnailGenerator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Event\Events;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

#[AsEventListener(event: Events::PRE_UPLOAD, method: 'onPreUpload')]
#[AsEventListener(event: Events::POST_UPLOAD, method: 'onPostUpload')]
#[AsEventListener(event: Events::PRE_REMOVE, method: 'onPreRemove')]
final class MediaVichListener
{
    public function __construct(
        private readonly MediaImageProcessor $processor,
        private readonly MediaThumbnailGenerator $thumbnails,
    ) {
    }

    public function onPreUpload(Event $event): void
    {
        $media = $event->getObject();
        if (!$media instanceof Media) {
            return;
        }

        $file = $media->getImageFile();
        if (!$file instanceof File) {
            return;
        }

        $processed = $this->processor->process($file->getPathname());
        $media->setWidth($processed->width);
        $media->setHeight($processed->height);
        $media->setImageFile(new ReplacingFile($processed->path, true, true, true));
    }

    public function onPostUpload(Event $event): void
    {
        $media = $event->getObject();
        if (!$media instanceof Media) {
            return;
        }

        $original = $media->getClientOriginalName();
        if ($original !== null && $original !== '') {
            $media->setOriginalName($this->processor->safeOriginalName($original));
        }

        if (trim($media->getAlt()) === '') {
            $stem = pathinfo($media->getOriginalName(), PATHINFO_FILENAME);
            $media->setAlt($stem !== '' ? $stem : 'Image');
        }

        $this->thumbnails->generate($media);
    }

    public function onPreRemove(Event $event): void
    {
        $media = $event->getObject();
        if (!$media instanceof Media) {
            return;
        }

        $this->thumbnails->delete($media->getFileName());
    }
}
