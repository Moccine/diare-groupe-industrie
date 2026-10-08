<?php

namespace App\Mailer;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class BrevoApiTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(
        private readonly HttpClientInterface $client,
        #[\SensitiveParameter]
        private readonly string $apiKey,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($dispatcher, $logger);
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();
        if (!$email instanceof Email) {
            throw new TransportException('Ce message ne peut pas être envoyé par l’API Brevo.');
        }

        $apiKey = trim($this->apiKey);
        if ($apiKey === '') {
            throw new TransportException('Clé API Brevo absente.');
        }

        $from = $email->getFrom()[0] ?? null;
        $html = $email->getHtmlBody();
        $subject = trim((string) $email->getSubject());
        if (!$from instanceof Address || $subject === '' || !is_string($html) || trim($html) === '') {
            throw new TransportException('Message incomplet : expéditeur, sujet et corps HTML sont obligatoires.');
        }

        $payload = [
            'sender' => $this->party($from),
            'to' => $this->parties($email->getTo()),
            'subject' => $subject,
            'htmlContent' => $html,
        ];

        $text = $email->getTextBody();
        if (is_string($text) && trim($text) !== '') {
            $payload['textContent'] = $text;
        }

        $cc = $this->parties($email->getCc());
        if ($cc !== []) {
            $payload['cc'] = $cc;
        }

        $bcc = $this->parties($email->getBcc());
        if ($bcc !== []) {
            $payload['bcc'] = $bcc;
        }

        $replyTo = $email->getReplyTo()[0] ?? null;
        if ($replyTo instanceof Address) {
            $payload['replyTo'] = $this->party($replyTo);
        }

        $attachments = $this->attachments($email);
        if ($attachments !== []) {
            $payload['attachment'] = $attachments;
        }

        if ($payload['to'] === []) {
            throw new TransportException('Destinataire absent.');
        }

        try {
            $response = $this->client->request('POST', self::ENDPOINT, [
                'headers' => [
                    'api-key' => $apiKey,
                    'accept' => 'application/json',
                    'content-type' => 'application/json',
                ],
                'json' => $payload,
            ]);
            $statusCode = $response->getStatusCode();
        } catch (ExceptionInterface $exception) {
            $this->getLogger()->error('Envoi Brevo injoignable.', [
                'error' => $exception::class,
            ]);

            throw new TransportException('L’API Brevo est injoignable.');
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $this->getLogger()->error('Envoi Brevo refusé.', [
                'status' => $statusCode,
            ]);

            throw new TransportException(sprintf('L’API Brevo a répondu HTTP %d.', $statusCode));
        }
    }

    public function __toString(): string
    {
        return 'brevo+api://default';
    }

    /**
     * @param list<Address> $addresses
     *
     * @return list<array{email: string, name: string}>
     */
    private function parties(array $addresses): array
    {
        $parties = [];
        foreach ($addresses as $address) {
            $parties[] = $this->party($address);
        }

        return $parties;
    }

    /** @return array{email: string, name: string} */
    private function party(Address $address): array
    {
        $email = $address->getAddress();

        return [
            'email' => $email,
            'name' => $address->getName() !== '' ? $address->getName() : $email,
        ];
    }

    /** @return list<array{name: string, content: string}> */
    private function attachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            if (!$attachment instanceof DataPart) {
                continue;
            }

            $attachments[] = [
                'name' => $attachment->getFilename() ?: 'piece-jointe',
                'content' => base64_encode($attachment->getBody()),
            ];
        }

        return $attachments;
    }
}
