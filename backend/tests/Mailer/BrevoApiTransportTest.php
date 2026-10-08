<?php

namespace App\Tests\Mailer;

use App\Mailer\BrevoApiTransport;
use App\Mailer\BrevoApiTransportFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class BrevoApiTransportTest extends TestCase
{
    public function testSendPostsTheApiKeyWithoutAUsername(): void
    {
        $captured = null;
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse('{"messageId":"brevo-123"}', ['http_code' => 201]);
        });

        $transport = new BrevoApiTransport($client, 'xkeysib-test', null, new NullLogger());
        $transport->send($this->email());

        self::assertSame('POST', $captured['method']);
        self::assertSame('https://api.brevo.com/v3/smtp/email', $captured['url']);
        self::assertSame('brevo+api://default', (string) $transport);

        $payload = json_decode((string) $captured['options']['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('no-reply@example.com', $payload['sender']['email']);
        self::assertSame('Diaré', $payload['sender']['name']);
        self::assertSame('to@example.com', $payload['to'][0]['email']);
        self::assertSame('Awa', $payload['to'][0]['name']);
        self::assertSame('Hello', $payload['subject']);
        self::assertSame('<p>Body</p>', $payload['htmlContent']);
        self::assertSame('Body', $payload['textContent']);
        self::assertSame('reply@example.com', $payload['replyTo']['email']);
        self::assertSame('xkeysib-test', $this->apiKeyHeader($captured['options']));
        self::assertStringNotContainsString('xkeysib-test', (string) $transport);
    }

    public function testMissingApiKeyDoesNotCallBrevo(): void
    {
        $client = new MockHttpClient();
        $transport = new BrevoApiTransport($client, '  ', null, new NullLogger());

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Clé API Brevo absente.');

        try {
            $transport->send($this->email());
        } finally {
            self::assertSame(0, $client->getRequestsCount());
        }
    }

    public function testFailureDoesNotExposeTheApiKey(): void
    {
        $client = new MockHttpClient([
            new MockResponse('api-key=xkeysib-secret', ['http_code' => 401]),
        ]);
        $transport = new BrevoApiTransport($client, 'xkeysib-secret', null, new NullLogger());

        try {
            $transport->send($this->email());
            self::fail('Une erreur HTTP devait être levée.');
        } catch (TransportException $exception) {
            self::assertSame('L’API Brevo a répondu HTTP 401.', $exception->getMessage());
            self::assertStringNotContainsString('xkeysib-secret', $exception->getMessage());
        }
    }

    public function testFactoryUsesTheConfiguredKey(): void
    {
        $factory = new BrevoApiTransportFactory('xkeysib-factory', null, new MockHttpClient(), new NullLogger());

        self::assertTrue($factory->supports(Dsn::fromString('brevo+api://default')));
        self::assertFalse($factory->supports(Dsn::fromString('smtp://localhost')));
        self::assertInstanceOf(BrevoApiTransport::class, $factory->create(Dsn::fromString('brevo+api://default')));
    }

    private function email(): Email
    {
        return (new Email())
            ->from(new Address('no-reply@example.com', 'Diaré'))
            ->to(new Address('to@example.com', 'Awa'))
            ->replyTo(new Address('reply@example.com', 'Contact'))
            ->subject('Hello')
            ->html('<p>Body</p>')
            ->text('Body');
    }

    /** @param array<string, mixed> $options */
    private function apiKeyHeader(array $options): string
    {
        $headers = $options['headers'] ?? [];
        if (is_array($headers)) {
            foreach ($headers as $header) {
                if (is_string($header) && str_starts_with(strtolower($header), 'api-key:')) {
                    return trim(substr($header, strlen('api-key:')));
                }
            }
        }

        $normalized = $options['normalized_headers']['api-key'][0] ?? '';

        return is_string($normalized) ? $normalized : '';
    }
}
