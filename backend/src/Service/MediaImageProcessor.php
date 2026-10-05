<?php

namespace App\Service;

use App\Media\MediaImageRules;
use App\Media\ProcessedMediaFile;

final class MediaImageProcessor
{
    public function process(string $path): ProcessedMediaFile
    {
        if (!is_file($path)) {
            throw new \RuntimeException('Le fichier image est introuvable.');
        }

        $size = filesize($path);
        if ($size === false || $size <= 0 || $size > MediaImageRules::MAX_BYTES) {
            throw new \RuntimeException('Le fichier dépasse le poids autorisé de 8 Mo.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        if (!in_array($mime, MediaImageRules::ALLOWED_MIME_TYPES, true)) {
            throw new \RuntimeException('Format non autorisé. Utilisez JPEG, PNG, WebP, GIF ou AVIF.');
        }

        $image = $this->open($path, $mime);
        if ($image instanceof \GdImage) {
            $image = $this->contain($image, MediaImageRules::MAX_EDGE, MediaImageRules::MAX_EDGE);
            $target = $this->tempPath('webp');
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $width = imagesx($image);
            $height = imagesy($image);
            if (!imagewebp($image, $target, 80)) {
                imagedestroy($image);
                @unlink($target);

                return $this->storeOriginal($path, $mime);
            }
            imagedestroy($image);

            return new ProcessedMediaFile($target, 'image/webp', $width, $height);
        }

        return $this->storeOriginal($path, $mime);
    }

    public function open(string $path, ?string $mime = null): ?\GdImage
    {
        $mime ??= (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            'image/gif' => @imagecreatefromgif($path),
            'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($path) : false,
            default => false,
        };

        if (!$image instanceof \GdImage) {
            return null;
        }

        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return $image;
    }

    public function contain(\GdImage $image, int $maxWidth, int $maxHeight): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width <= 0 || $height <= 0) {
            return $image;
        }

        $ratio = min($maxWidth / $width, $maxHeight / $height, 1);
        if ($ratio >= 1) {
            return $image;
        }

        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $resized;
    }

    public function safeOriginalName(string $name): string
    {
        $base = basename(str_replace('\\', '/', $name));
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base) ?? 'image';
        $base = trim($base, '.-');

        return $base !== '' ? mb_substr($base, 0, 180) : 'image';
    }

    private function storeOriginal(string $path, string $mime): ProcessedMediaFile
    {
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
            default => 'webp',
        };
        $target = $this->tempPath($extension);
        if (!copy($path, $target)) {
            throw new \RuntimeException('Impossible d’enregistrer le média.');
        }

        $info = @getimagesize($target);

        return new ProcessedMediaFile(
            $target,
            $mime,
            is_array($info) ? $info[0] : null,
            is_array($info) ? $info[1] : null,
        );
    }

    private function tempPath(string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dgi-media-');
        if ($path === false) {
            throw new \RuntimeException('Impossible de préparer le fichier image.');
        }

        $target = $path.'.'.$extension;
        if (!rename($path, $target)) {
            @unlink($path);
            throw new \RuntimeException('Impossible de préparer le fichier image.');
        }

        return $target;
    }
}
