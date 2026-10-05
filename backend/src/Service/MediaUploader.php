<?php

namespace App\Service;

use App\Entity\Media;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class MediaUploader
{
    private const MAX_BYTES = 8_388_608;
    private const MAX_EDGE = 2000;

    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
    ];

    public function __construct(
        private readonly string $mediaUploadDir,
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

        $size = filesize($path);
        if ($size === false || $size <= 0 || $size > self::MAX_BYTES) {
            throw new \RuntimeException('Le fichier dépasse le poids autorisé de 8 Mo.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw new \RuntimeException('Format non autorisé. Utilisez JPEG, PNG, WebP, GIF ou AVIF.');
        }

        $safeOriginal = $this->safeOriginalName($originalName);
        if (!is_dir($this->mediaUploadDir) && !mkdir($this->mediaUploadDir, 0775, true) && !is_dir($this->mediaUploadDir)) {
            throw new \RuntimeException('Le dossier de médias est inaccessible.');
        }

        $stored = $this->store($path, $mime);
        $media = (new Media())
            ->setFileName($stored['fileName'])
            ->setOriginalName($safeOriginal)
            ->setAlt(trim($alt) !== '' ? trim($alt) : pathinfo($safeOriginal, PATHINFO_FILENAME))
            ->setTitle($title)
            ->setCaption($caption)
            ->setMimeType($stored['mimeType'])
            ->setSize($stored['size'])
            ->setWidth($stored['width'])
            ->setHeight($stored['height']);

        return $media;
    }

    public function deleteStoredFile(?string $fileName): void
    {
        if ($fileName === null || $fileName === '') {
            return;
        }

        $base = basename($fileName);
        if ($base !== $fileName || str_contains($base, '..')) {
            return;
        }

        $path = $this->mediaUploadDir.'/'.$base;
        if (is_file($path)) {
            unlink($path);
        }
    }

    /** @return array{fileName: string, mimeType: string, size: int, width: ?int, height: ?int} */
    private function store(string $path, string $mime): array
    {
        $image = $this->createImage($path, $mime);
        if ($image instanceof \GdImage) {
            $image = $this->resize($image);
            $fileName = bin2hex(random_bytes(16)).'.webp';
            $target = $this->mediaUploadDir.'/'.$fileName;
            imagealphablending($image, false);
            imagesavealpha($image, true);
            if (!imagewebp($image, $target, 80)) {
                imagedestroy($image);

                return $this->storeOriginal($path, $mime);
            }

            $width = imagesx($image);
            $height = imagesy($image);
            imagedestroy($image);
            $size = filesize($target);

            return [
                'fileName' => $fileName,
                'mimeType' => 'image/webp',
                'size' => $size === false ? 0 : $size,
                'width' => $width,
                'height' => $height,
            ];
        }

        return $this->storeOriginal($path, $mime);
    }

    /** @return array{fileName: string, mimeType: string, size: int, width: ?int, height: ?int} */
    private function storeOriginal(string $path, string $mime): array
    {
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
            default => 'webp',
        };
        $fileName = bin2hex(random_bytes(16)).'.'.$extension;
        $target = $this->mediaUploadDir.'/'.$fileName;
        if (!copy($path, $target)) {
            throw new \RuntimeException('Impossible d’enregistrer le média.');
        }

        $info = @getimagesize($target);
        $size = filesize($target);

        return [
            'fileName' => $fileName,
            'mimeType' => $mime,
            'size' => $size === false ? 0 : $size,
            'width' => is_array($info) ? $info[0] : null,
            'height' => is_array($info) ? $info[1] : null,
        ];
    }

    private function createImage(string $path, string $mime): ?\GdImage
    {
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

    private function resize(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $edge = max($width, $height);
        if ($edge <= self::MAX_EDGE) {
            return $image;
        }

        $ratio = self::MAX_EDGE / $edge;
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

    private function safeOriginalName(string $name): string
    {
        $base = basename(str_replace('\\', '/', $name));
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base) ?? 'image';
        $base = trim($base, '.-');

        return $base !== '' ? mb_substr($base, 0, 180) : 'image';
    }
}
