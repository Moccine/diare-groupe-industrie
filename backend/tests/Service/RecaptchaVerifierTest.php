<?php

namespace App\Tests\Service;

use App\Service\RecaptchaVerifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RecaptchaVerifierTest extends TestCase
{
    public function testDisabledVerifierDoesNotCallGoogle(): void
    {
        $client = $this->failingClient();
        $decision = $this->verifier($client, enabled: false)->verify('anything', '127.0.0.1');

        self::assertTrue($decision->isAccepted());
        self::assertSame(0, $client->getRequestsCount());
    }

    public function testValidTokenIsAccepted(): void
    {
        $client = new MockHttpClient([new MockResponse($this->payload(true, 0.9))]);

        self::assertTrue($this->verifier($client)->verify('token-ok', '127.0.0.1')->isAccepted());
        self::assertSame(1, $client->getRequestsCount());
    }

    public function testInvalidTokenIsRejected(): void
    {
        $client = new MockHttpClient([new MockResponse($this->payload(false, 0.1, ['invalid-input-response']))]);

        self::assertFalse($this->verifier($client)->verify('token-ko', '127.0.0.1')->isAccepted());
    }

    public function testLowScoreIsRejected(): void
    {
        $client = new MockHttpClient([new MockResponse($this->payload(true, 0.2))]);

        self::assertFalse($this->verifier($client)->verify('token-low', '127.0.0.1')->isAccepted());
    }

    public function testTimeoutIsRejected(): void
    {
        $client = new MockHttpClient(static function (): never {
            throw new TimeoutException('Idle timeout reached.');
        });

        self::assertFalse($this->verifier($client)->verify('token', '127.0.0.1')->isAccepted());
    }

    public function testUnreachableApiIsRejected(): void
    {
        $client = new MockHttpClient(static function (): never {
            throw new TransportException('Could not resolve host.');
        });

        self::assertFalse($this->verifier($client)->verify('token', '127.0.0.1')->isAccepted());
    }

    public function testInvalidJsonIsRejected(): void
    {
        $client = new MockHttpClient([new MockResponse('not-json')]);

        self::assertFalse($this->verifier($client)->verify('token', '127.0.0.1')->isAccepted());
    }

    public function testMissingSecretDoesNotCallGoogle(): void
    {
        $client = $this->failingClient();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $verifier = new RecaptchaVerifier($client, $logger, true, 'site-key', '', 0.5);

        self::assertFalse($verifier->verify('token', '127.0.0.1')->isAccepted());
        self::assertSame('', $verifier->siteKey());
        self::assertSame(0, $client->getRequestsCount());
    }

    private function verifier(MockHttpClient $client, bool $enabled = true): RecaptchaVerifier
    {
        return new RecaptchaVerifier(
            $client,
            $this->createMock(LoggerInterface::class),
            $enabled,
            'site-key',
            'secret-key',
            0.5,
        );
    }

    private function failingClient(): MockHttpClient
    {
        return new MockHttpClient(static function (): never {
            throw new \RuntimeException('Google ne doit pas être contacté.');
        });
    }

    /** @param list<string> $errors */
    private function payload(bool $success, float $score, array $errors = []): string
    {
        $json = json_encode([
            'success' => $success,
            'score' => $score,
            'action' => 'contact',
            'error-codes' => $errors,
        ], JSON_THROW_ON_ERROR);

        return $json;
    }
}
