<?php

namespace App\Tests\Service;

use App\Exception\JobApplicationCvException;
use App\Service\JobApplicationCvStorage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class JobApplicationCvStorageTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $directory = sys_get_temp_dir().'/dgi-cv-'.bin2hex(random_bytes(4));
        if (!mkdir($directory, 0770, true) && !is_dir($directory)) {
            self::fail('Dossier de test impossible à créer.');
        }
        $this->directory = $directory;
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        foreach (glob($this->directory.'/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->directory);
    }

    public function testValidPdfIsStoredUnderAServerGeneratedName(): void
    {
        $stored = $this->storage()->store($this->upload($this->pdf(1200), '../../secret cv.pdf'));

        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}\.pdf$/', $stored->storedFilename);
        self::assertSame(1200, $stored->size);
        self::assertStringNotContainsString('/', $stored->originalFilename);
        self::assertStringNotContainsString('..', $stored->originalFilename);
        $path = $this->storage()->absolutePath($stored->storedFilename);
        self::assertStringStartsWith($this->directory.DIRECTORY_SEPARATOR, $path);
        self::assertSame('%PDF-', file_get_contents($path, false, null, 0, 5));
    }

    public function testPdfOfExactlyFiveMegabytesIsAccepted(): void
    {
        $stored = $this->storage()->store($this->upload($this->pdf(5242880), 'cv.pdf'));

        self::assertSame(5242880, $stored->size);
    }

    public function testPdfLargerThanFiveMegabytesIsRejected(): void
    {
        $this->expectException(JobApplicationCvException::class);
        $this->storage()->store($this->upload($this->pdf(5242881), 'gros.pdf'));
    }

    public function testRenamedTextFileIsRejected(): void
    {
        $path = $this->write('Ce fichier n’est pas un PDF.');

        $this->expectException(JobApplicationCvException::class);
        $this->storage()->store($this->upload($path, 'cv.pdf'));
    }

    public function testEmptyFileIsRejected(): void
    {
        $this->expectException(JobApplicationCvException::class);
        $this->storage()->store($this->upload($this->write(''), 'vide.pdf'));
    }

    public function testPngDisguisedAsPdfIsRejected(): void
    {
        $this->expectException(JobApplicationCvException::class);
        $this->storage()->store($this->upload($this->write("\x89PNG\r\n\x1a\n".str_repeat('x', 40)), 'photo.pdf'));
    }

    public function testTraversalIsRejectedWhenReading(): void
    {
        $storage = $this->storage();
        foreach (['../etc/passwd', '..\\secret.pdf', 'cv.pdf', 'not-a-uuid.pdf', 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee.pdf/../x.pdf'] as $name) {
            try {
                $storage->absolutePath($name);
                self::fail($name.' aurait dû être refusé.');
            } catch (JobApplicationCvException) {
                self::assertTrue(true);
            }
        }
    }

    public function testUnwritableDirectoryDoesNotLeaveAStoredFile(): void
    {
        $blocker = tempnam(sys_get_temp_dir(), 'cvblock');
        self::assertIsString($blocker);
        $storage = new JobApplicationCvStorage(new NullLogger(), $blocker);

        try {
            $storage->store($this->upload($this->pdf(900), 'cv.pdf'));
            self::fail('Le stockage aurait dû échouer.');
        } catch (JobApplicationCvException) {
            self::assertFileDoesNotExist($blocker.'.pdf');
        } finally {
            unlink($blocker);
        }
    }

    private function storage(): JobApplicationCvStorage
    {
        return new JobApplicationCvStorage(new NullLogger(), $this->directory);
    }

    private function upload(string $path, string $original): UploadedFile
    {
        return new UploadedFile($path, $original, 'application/pdf', null, true);
    }

    private function pdf(int $size): string
    {
        $header = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
        if (strlen($header) > $size) {
            $size = strlen($header);
        }

        return $this->write(str_pad($header, $size, ' '));
    }

    private function write(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cvsrc');
        self::assertIsString($path);
        $target = $path.'.pdf';
        file_put_contents($target, $contents);
        unlink($path);

        return $target;
    }
}
