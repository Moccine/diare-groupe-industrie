<?php

namespace App\Vich;

use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\NamerInterface;

final class MediaFileNamer implements NamerInterface
{
    public function name(object $object, PropertyMapping $mapping): string
    {
        $file = $mapping->getFile($object);
        $extension = $file instanceof File ? strtolower((string) $file->guessExtension()) : '';
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }
        if (!preg_match('/^[a-z0-9]{2,5}$/', $extension)) {
            $extension = 'webp';
        }

        return bin2hex(random_bytes(16)).'.'.$extension;
    }
}
