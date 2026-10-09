<?php

namespace App\Service;

use App\Exception\JobApplicationCvException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final class JobApplicationCvStorage
{
    public const MAX_BYTES = 5242880;

    private const STORED_NAME = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.pdf$/';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $jobApplicationCvDir,
    ) {
    }

    public function store(UploadedFile $file): StoredCv
    {
        $this->assertValid($file);
        $this->ensureDirectory();

        $storedFilename = Uuid::v4()->toRfc4122().'.pdf';
        $target = $this->directory().DIRECTORY_SEPARATOR.$storedFilename;

        try {
            $file->move($this->directory(), $storedFilename);
        } catch (\Throwable $exception) {
            $this->logger->error('Stockage du CV impossible.', [
                'error' => $exception::class,
            ]);
            if (is_file($target)) {
                unlink($target);
            }

            throw new JobApplicationCvException('Le CV n’a pas pu être enregistré. Sélectionnez à nouveau votre fichier.');
        }

        if (!is_file($target)) {
            throw new JobApplicationCvException('Le CV n’a pas pu être enregistré. Sélectionnez à nouveau votre fichier.');
        }

        @chmod($target, 0640);
        $size = filesize($target);
        if ($size === false || $size < 1 || $size > self::MAX_BYTES) {
            unlink($target);

            throw new JobApplicationCvException('Le CV n’a pas pu être enregistré. Sélectionnez à nouveau votre fichier.');
        }

        return new StoredCv(
            $storedFilename,
            $this->originalFilename($file),
            'application/pdf',
            $size,
        );
    }

    public function absolutePath(string $storedFilename): string
    {
        if (!preg_match(self::STORED_NAME, $storedFilename)) {
            throw new JobApplicationCvException('CV introuvable.');
        }

        $directory = realpath($this->directory());
        $path = realpath($this->directory().DIRECTORY_SEPARATOR.$storedFilename);
        if ($directory === false || $path === false || !is_file($path)) {
            throw new JobApplicationCvException('CV introuvable.');
        }

        $prefix = $directory.DIRECTORY_SEPARATOR;
        if (!str_starts_with($path, $prefix)) {
            throw new JobApplicationCvException('CV introuvable.');
        }

        return $path;
    }

    public function exists(string $storedFilename): bool
    {
        try {
            return is_file($this->absolutePath($storedFilename));
        } catch (JobApplicationCvException) {
            return false;
        }
    }

    public function delete(?string $storedFilename): void
    {
        if ($storedFilename === null || $storedFilename === '') {
            return;
        }

        try {
            $path = $this->absolutePath($storedFilename);
        } catch (JobApplicationCvException) {
            return;
        }

        if (!unlink($path)) {
            $this->logger->error('Suppression du CV impossible.', [
                'file' => $storedFilename,
            ]);
        }
    }

    public function directory(): string
    {
        return $this->jobApplicationCvDir;
    }

    private function assertValid(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new JobApplicationCvException('Le CV n’a pas pu être lu. Sélectionnez à nouveau votre fichier.');
        }

        $pathname = $file->getPathname();
        if ($pathname === '' || !is_file($pathname)) {
            throw new JobApplicationCvException('Le CV n’a pas pu être lu. Sélectionnez à nouveau votre fichier.');
        }

        $size = filesize($pathname);
        if ($size === false || $size < 1) {
            throw new JobApplicationCvException('Le fichier CV est vide.');
        }

        if ($size > self::MAX_BYTES) {
            throw new JobApplicationCvException('Le CV ne doit pas dépasser 5 Mo.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'pdf') {
            throw new JobApplicationCvException('Le CV doit être un fichier PDF.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($pathname);
        if (!is_string($mime) || !in_array($mime, ['application/pdf', 'application/x-pdf'], true)) {
            throw new JobApplicationCvException('Le fichier sélectionné n’est pas un PDF valide.');
        }

        $handle = fopen($pathname, 'rb');
        if ($handle === false) {
            throw new JobApplicationCvException('Le CV n’a pas pu être lu. Sélectionnez à nouveau votre fichier.');
        }

        $header = fread($handle, 5);
        fclose($handle);
        if ($header !== '%PDF-') {
            throw new JobApplicationCvException('Le fichier sélectionné n’est pas un PDF valide.');
        }
    }

    private function originalFilename(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = preg_replace('/[^A-Za-z0-9._ -]+/', '', $name) ?? '';
        $name = trim($name);
        if ($name === '' || !str_ends_with(strtolower($name), '.pdf')) {
            return 'cv.pdf';
        }

        if (strlen($name) > 180) {
            $name = substr($name, -180);
        }

        return $name;
    }

    private function ensureDirectory(): void
    {
        $directory = $this->directory();
        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, 0770, true) && !is_dir($directory)) {
            $this->logger->error('Dossier privé des CV inaccessible.');

            throw new JobApplicationCvException('Le CV n’a pas pu être enregistré. Sélectionnez à nouveau votre fichier.');
        }
    }
}
