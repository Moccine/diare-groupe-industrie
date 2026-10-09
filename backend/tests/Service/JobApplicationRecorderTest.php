<?php

namespace App\Tests\Service;

use App\Entity\JobApplication;
use App\Service\JobApplicationCvStorage;
use App\Service\JobApplicationRecorder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class JobApplicationRecorderTest extends TestCase
{
    public function testDatabaseFailureDeletesTheStoredCv(): void
    {
        $directory = sys_get_temp_dir().'/dgi-cv-recorder-'.bin2hex(random_bytes(4));
        mkdir($directory, 0770, true);
        $storage = new JobApplicationCvStorage(new NullLogger(), $directory);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())->method('persist');
        $manager->expects(self::once())->method('flush')->willThrowException(new \RuntimeException('base indisponible'));
        $recorder = new JobApplicationRecorder($manager, $storage, new NullLogger(), '');

        $path = tempnam(sys_get_temp_dir(), 'cvok');
        self::assertIsString($path);
        $pdf = $path.'.pdf';
        file_put_contents($pdf, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
        unlink($path);

        try {
            $recorder->record(new JobApplication(), new UploadedFile($pdf, 'cv.pdf', 'application/pdf', null, true));
            self::fail('La persistance aurait dû échouer.');
        } catch (\RuntimeException $exception) {
            self::assertSame('base indisponible', $exception->getMessage());
            self::assertSame([], glob($directory.'/*') ?: []);
        } finally {
            if (is_file($pdf)) {
                unlink($pdf);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }
}
