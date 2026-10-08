<?php

namespace App\Mailer;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Mailer\Exception\IncompleteDsnException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AutoconfigureTag('mailer.transport_factory')]
final class BrevoApiTransportFactory extends AbstractTransportFactory
{
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $brevoApiKey,
        ?EventDispatcherInterface $dispatcher = null,
        ?HttpClientInterface $client = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($dispatcher, $client, $logger);
    }

    public function create(Dsn $dsn): TransportInterface
    {
        if (!$this->supports($dsn)) {
            throw new UnsupportedSchemeException($dsn, 'brevo', $this->getSupportedSchemes());
        }

        if (!$this->client instanceof HttpClientInterface) {
            throw new IncompleteDsnException('Le client HTTP est indisponible.');
        }

        return new BrevoApiTransport($this->client, $this->brevoApiKey, $this->dispatcher, $this->logger);
    }

    protected function getSupportedSchemes(): array
    {
        return ['brevo+api'];
    }
}
