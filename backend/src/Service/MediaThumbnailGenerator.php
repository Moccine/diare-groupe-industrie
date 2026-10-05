<?php

namespace App\Service;

use App\Entity\Media;
use Psr\Log\LoggerInterface;

final class MediaThumbnailGenerator
{
    public const MAX_WIDTH = 320;
    public const MAX_HEIGHT = 240;

    public function __construct(
        private readonly string $mediaUploadDir,
        private readonly MediaImageProcessor $processor,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function generate(Media $media): bool
    {
        $source = $this->sourcePath($media);
        if ($source === null) {
            return false;
        }

        try {
            $image = $this->processor->open($source);
            if (!$image instanceof \GdImage) {
                $this->logger->warning('Miniature ignorée : image illisible.', ['file' => $media->getFileName()]);

                return false;
            }

            $image = $this->processor->contain($image, self::MAX_WIDTH, self::MAX_HEIGHT);
            $target = $this->absoluteThumbnailPath($media);
            $directory = dirname($target);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                imagedestroy($image);
                $this->logger->error('Dossier de miniatures inaccessible.');

                return false;
            }

            imagealphablending($image, false);
            imagesavealpha($image, true);
            $saved = imagewebp($image, $target, 80);
            imagedestroy($image);
            if (!$saved) {
                $this->logger->error('Écriture de la miniature impossible.', ['file' => $media->getFileName()]);

                return false;
            }

            return true;
        } catch (\Throwable $exception) {
            $this->logger->error('Génération de miniature interrompue.', [
                'file' => $media->getFileName(),
                'exception' => $exception,
            ]);

            return false;
        }
    }

    public function delete(?string $fileName): void
    {
        $path = $this->absoluteThumbnailPathFromFileName($fileName);
        if ($path !== null && is_file($path)) {
            unlink($path);
        }
    }

    public function sourcePath(Media $media): ?string
    {
        $fileName = $this->safeFileName($media->getFileName());
        if ($fileName === null) {
            return null;
        }

        $path = $this->mediaUploadDir.'/'.$fileName;

        return is_file($path) ? $path : null;
    }

    public function absoluteThumbnailPath(Media $media): string
    {
        $path = $this->absoluteThumbnailPathFromFileName($media->getFileName());
        if ($path === null) {
            throw new \RuntimeException('Nom de média invalide.');
        }

        return $path;
    }

    public function thumbnailExists(Media $media): bool
    {
        $path = $this->absoluteThumbnailPathFromFileName($media->getFileName());

        return $path !== null && is_file($path);
    }

    private function absoluteThumbnailPathFromFileName(?string $fileName): ?string
    {
        $fileName = $this->safeFileName($fileName);
        if ($fileName === null) {
            return null;
        }

        $stem = pathinfo($fileName, PATHINFO_FILENAME);
        if ($stem === '') {
            return null;
        }

        return $this->mediaUploadDir.'/thumbnails/'.$stem.'.webp';
    }

    private function safeFileName(?string $fileName): ?string
    {
        if ($fileName === null || $fileName === '') {
            return null;
        }

        $base = basename($fileName);
        if ($base !== $fileName || str_contains($base, '..')) {
            return null;
        }

        return $base;
    }
}
