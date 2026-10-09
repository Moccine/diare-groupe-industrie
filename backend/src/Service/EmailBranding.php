<?php

namespace App\Service;

use App\Entity\Media;
use App\Entity\SiteSettings;

/**
 * Adresse publique et logo utilisables dans un e-mail.
 * Une URL locale, privée ou non HTTPS ne doit jamais être écrite dans le message.
 */
final class EmailBranding
{
    public function __construct(
        private readonly string $defaultUri,
    ) {
    }

    public function siteUrl(): ?string
    {
        return $this->publicHttpsOrigin($this->defaultUri);
    }

    /**
     * @return array{url: string, width: int, height: int}|null
     */
    public function logo(SiteSettings $settings): ?array
    {
        $origin = $this->siteUrl();
        if ($origin === null) {
            return null;
        }

        $media = $settings->getLogo();
        $path = $this->logoPath($media);

        return [
            'url' => $origin.$path,
            'width' => $this->displayWidth($media),
            'height' => $this->displayHeight($media),
        ];
    }

    public function color(?string $color, string $fallback): string
    {
        $normalized = $this->normalizeColor($color);
        if ($normalized !== null) {
            return $normalized;
        }

        return $this->normalizeColor($fallback) ?? '#185424';
    }

    private function logoPath(?Media $logo): string
    {
        $fileName = $logo?->getFileName() ?? '';
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,200}$/', $fileName) === 1) {
            return '/uploads/media/'.$fileName;
        }

        return '/brand/logo.png';
    }

    private function displayWidth(?Media $logo): int
    {
        return $this->displaySize($logo)[0];
    }

    private function displayHeight(?Media $logo): int
    {
        return $this->displaySize($logo)[1];
    }

    /** @return array{0: int, 1: int} */
    private function displaySize(?Media $logo): array
    {
        $width = $logo?->getWidth();
        $height = $logo?->getHeight();
        if ($width === null || $height === null || $width < 1 || $height < 1) {
            $width = 635;
            $height = 704;
        }

        $scale = min(64 / $width, 64 / $height, 1);

        return [
            max(1, (int) round($width * $scale)),
            max(1, (int) round($height * $scale)),
        ];
    }

    private function publicHttpsOrigin(string $uri): ?string
    {
        $uri = trim($uri);
        if ($uri === '' || preg_match('/\s/', $uri) === 1) {
            return null;
        }

        $parts = parse_url($uri);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($scheme !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass']) || $this->isLocalHost($host)) {
            return null;
        }

        $port = isset($parts['port']) && (int) $parts['port'] !== 443 ? ':'.(int) $parts['port'] : '';

        return 'https://'.$host.$port;
    }

    private function isLocalHost(string $host): bool
    {
        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)) {
            return true;
        }

        if (str_ends_with($host, '.local') || str_ends_with($host, '.localhost')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    private function normalizeColor(?string $color): ?string
    {
        $color = trim((string) $color);
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color) !== 1) {
            return null;
        }

        return $color;
    }
}
