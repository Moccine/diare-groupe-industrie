<?php

namespace App\EventListener;

use App\Entity\Media;
use App\Service\MediaUploader;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: Media::class)]
final class MediaRemovalListener
{
    public function __construct(
        private readonly MediaUploader $mediaUploader,
    ) {
    }

    public function preRemove(Media $media): void
    {
        $this->mediaUploader->deleteStoredFile($media->getFileName());
    }
}
