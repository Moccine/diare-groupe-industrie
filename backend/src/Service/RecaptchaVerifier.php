<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RecaptchaVerifier
{
    private const ENDPOINT = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly bool $enabled,
        private readonly string $siteKey,
        #[\SensitiveParameter]
        private readonly string $secretKey,
        private readonly float $minScore,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->siteKey !== '' && $this->secretKey !== '';
    }

    public function siteKey(): string
    {
        return $this->isEnabled() ? $this->siteKey : '';
    }

    public function verify(?string $token, ?string $remoteIp): RecaptchaDecision
    {
        if (!$this->enabled) {
            return RecaptchaDecision::accepted();
        }

        if ($this->siteKey === '' || $this->secretKey === '') {
            $this->logger->warning('reCAPTCHA est activé sans clé utilisable. La vérification est refusée.');

            return RecaptchaDecision::rejected();
        }

        $token = trim((string) $token);
        if ($token === '') {
            $this->logger->info('Jeton reCAPTCHA absent.');

            return RecaptchaDecision::rejected();
        }

        try {
            $response = $this->httpClient->request('POST', self::ENDPOINT, [
                'body' => [
                    'secret' => $this->secretKey,
                    'response' => $token,
                    'remoteip' => $remoteIp ?? '',
                ],
                'timeout' => 5,
            ]);
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            $this->logger->error('Vérification reCAPTCHA injoignable.', [
                'error' => $exception::class,
            ]);

            return RecaptchaDecision::rejected();
        }

        if ($status !== Response::HTTP_OK) {
            $this->logger->error('Vérification reCAPTCHA refusée par le service distant.', [
                'status' => $status,
            ]);

            return RecaptchaDecision::rejected();
        }

        try {
            $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->logger->error('Réponse reCAPTCHA illisible.');

            return RecaptchaDecision::rejected();
        }

        if (!is_array($payload) || ($payload['success'] ?? false) !== true) {
            $codes = $payload['error-codes'] ?? [];
            $this->logger->info('Jeton reCAPTCHA refusé.', [
                'codes' => is_array($codes) ? array_values(array_filter($codes, 'is_string')) : [],
            ]);

            return RecaptchaDecision::rejected();
        }

        $action = $payload['action'] ?? null;
        if (is_string($action) && $action !== '' && $action !== 'contact') {
            $this->logger->info('Action reCAPTCHA inattendue.');

            return RecaptchaDecision::rejected();
        }

        if (isset($payload['score']) && is_numeric($payload['score']) && (float) $payload['score'] < $this->minScore) {
            $this->logger->info('Score reCAPTCHA insuffisant.', [
                'score' => (float) $payload['score'],
            ]);

            return RecaptchaDecision::rejected();
        }

        return RecaptchaDecision::accepted();
    }
}
