<?php

namespace App\Tests\Service;

use App\Entity\JobApplication;
use App\Service\JobApplicationSubmissionLock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class JobApplicationSubmissionLockTest extends TestCase
{
    public function testOverlappingSubmissionsWaitForTheSameCandidate(): void
    {
        $email = 'race-'.bin2hex(random_bytes(4)).'@example.com';
        $application = (new JobApplication())
            ->setEmail($email)
            ->setIsSpontaneous(true)
            ->setDesiredRole('Maintenance');
        $autoload = dirname(__DIR__, 2).'/vendor/autoload.php';
        $code = 'require '.var_export($autoload, true).';'
            .'$lock = new App\\Service\\JobApplicationSubmissionLock();'
            .'$application = (new App\\Entity\\JobApplication())->setEmail('.var_export($email, true).')->setIsSpontaneous(true)->setDesiredRole("Maintenance");'
            .'$lock->exclusive($application, function () { fwrite(STDOUT, "held\n"); sleep(1); });';
        $process = new Process([PHP_BINARY, '-r', $code]);
        $process->setTimeout(10);
        $process->start();

        $output = '';
        $deadline = microtime(true) + 5;
        while (microtime(true) < $deadline && !str_contains($output, 'held')) {
            $output .= $process->getIncrementalOutput();
            if (!$process->isRunning()) {
                break;
            }
            usleep(20000);
        }

        self::assertStringContainsString('held', $output, $process->getErrorOutput());

        $started = microtime(true);
        $result = (new JobApplicationSubmissionLock())->exclusive($application, static fn (): string => 'second');
        $elapsed = microtime(true) - $started;

        self::assertSame('second', $result);
        self::assertGreaterThan(0.4, $elapsed);
        $process->wait();
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }
}
