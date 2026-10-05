<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Repository\SiteSettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SiteSettingsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class SiteSettings
{
    use TimestampableTrait;

    /** Disque du logo officiel. */
    public const PRIMARY = '#185424';

    /** Vert du logo, assombri pour les grands aplats. */
    public const SECONDARY = '#0E3A18';

    /** Lime du logo : accent, icônes et éléments interactifs. */
    public const ACCENT = '#90B43C';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $companyName = 'Diaré Groupe Industrie';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Media $logo = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Media $favicon = null;

    #[ORM\Column(length: 7)]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/')]
    private string $primaryColor = self::PRIMARY;

    #[ORM\Column(length: 7)]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/')]
    private string $secondaryColor = self::SECONDARY;

    #[ORM\Column(length: 7)]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/')]
    private string $accentColor = self::ACCENT;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 9, scale: 6, nullable: true)]
    #[Assert\Regex(pattern: '/^-?\d{1,3}(?:\.\d{1,6})?$/')]
    #[Assert\Range(min: -90, max: 90)]
    private ?string $mapLatitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 9, scale: 6, nullable: true)]
    #[Assert\Regex(pattern: '/^-?\d{1,3}(?:\.\d{1,6})?$/')]
    #[Assert\Range(min: -180, max: 180)]
    private ?string $mapLongitude = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1, max: 19)]
    private ?int $mapZoom = 15;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $mapLabel = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url]
    private ?string $facebook = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url]
    private ?string $linkedin = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url]
    private ?string $instagram = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url]
    private ?string $youtube = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $footerText = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $copyright = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $headerCtaLabel = 'Contact';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $headerCtaUrl = '/contact';

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $metaDescription = null;

    public function __construct()
    {
        $this->initializeTimestamps();
    }

    public static function fallback(): self
    {
        return (new self())
            ->setCompanyName('Diaré Groupe Industrie')
            ->setPrimaryColor(self::PRIMARY)
            ->setSecondaryColor(self::SECONDARY)
            ->setAccentColor(self::ACCENT)
            ->setHeaderCtaLabel('Contact')
            ->setHeaderCtaUrl('/contact');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompanyName(): string
    {
        return $this->companyName;
    }

    public function setCompanyName(string $companyName): static
    {
        $this->companyName = $companyName;

        return $this;
    }

    public function getLogo(): ?Media
    {
        return $this->logo;
    }

    public function setLogo(?Media $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function getFavicon(): ?Media
    {
        return $this->favicon;
    }

    public function setFavicon(?Media $favicon): static
    {
        $this->favicon = $favicon;

        return $this;
    }

    public function getPrimaryColor(): string
    {
        return $this->primaryColor;
    }

    public function setPrimaryColor(string $primaryColor): static
    {
        $this->primaryColor = $primaryColor;

        return $this;
    }

    public function getSecondaryColor(): string
    {
        return $this->secondaryColor;
    }

    public function setSecondaryColor(string $secondaryColor): static
    {
        $this->secondaryColor = $secondaryColor;

        return $this;
    }

    public function getAccentColor(): string
    {
        return $this->accentColor;
    }

    public function setAccentColor(string $accentColor): static
    {
        $this->accentColor = $accentColor;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email !== null && trim($email) === '' ? null : $email;

        return $this;
    }

    public function hasPublicEmail(): bool
    {
        return $this->email !== null && filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getPhoneHref(): ?string
    {
        if ($this->phone === null || trim($this->phone) === '') {
            return null;
        }

        $digits = preg_replace('/[^\d+]/', '', $this->phone) ?? '';

        return $digits !== '' ? 'tel:'.$digits : null;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getMapLatitude(): ?string
    {
        return $this->mapLatitude;
    }

    public function setMapLatitude(float|int|string|null $mapLatitude): static
    {
        $this->mapLatitude = self::normalizeCoordinate($mapLatitude);

        return $this;
    }

    public function getMapLongitude(): ?string
    {
        return $this->mapLongitude;
    }

    public function setMapLongitude(float|int|string|null $mapLongitude): static
    {
        $this->mapLongitude = self::normalizeCoordinate($mapLongitude);

        return $this;
    }

    public function getMapZoom(): ?int
    {
        return $this->mapZoom;
    }

    public function setMapZoom(int|string|null $mapZoom): static
    {
        if ($mapZoom === null || $mapZoom === '') {
            $this->mapZoom = null;

            return $this;
        }

        $this->mapZoom = (int) $mapZoom;

        return $this;
    }

    public function resolvedMapZoom(): int
    {
        $zoom = $this->mapZoom ?? 15;

        return max(1, min(19, $zoom));
    }

    public function getMapLabel(): ?string
    {
        return $this->mapLabel;
    }

    public function setMapLabel(?string $mapLabel): static
    {
        $this->mapLabel = self::blankToNull($mapLabel);

        return $this;
    }

    public function getMapDisplayLabel(): string
    {
        $label = trim((string) $this->mapLabel);

        return $label !== '' ? $label : $this->companyName;
    }

    public function hasMap(): bool
    {
        return $this->coordinate($this->mapLatitude) !== null
            && $this->coordinate($this->mapLongitude) !== null;
    }

    public function getMapEmbedUrl(): ?string
    {
        $lat = $this->coordinate($this->mapLatitude);
        $lon = $this->coordinate($this->mapLongitude);
        if ($lat === null || $lon === null) {
            return null;
        }

        [$minLon, $minLat, $maxLon, $maxLat] = $this->boundingBox($lat, $lon, $this->resolvedMapZoom(), 900, 460);
        $bbox = implode(',', [
            self::formatDegree($minLon),
            self::formatDegree($minLat),
            self::formatDegree($maxLon),
            self::formatDegree($maxLat),
        ]);

        return 'https://www.openstreetmap.org/export/embed.html?bbox='.rawurlencode($bbox)
            .'&layer=mapnik&marker='.rawurlencode(self::formatDegree($lat).','.self::formatDegree($lon));
    }

    public function getMapDirectionsUrl(): ?string
    {
        $lat = $this->coordinate($this->mapLatitude);
        $lon = $this->coordinate($this->mapLongitude);
        if ($lat === null || $lon === null) {
            return null;
        }

        return 'https://www.openstreetmap.org/directions?to='.rawurlencode(self::formatDegree($lat).','.self::formatDegree($lon));
    }

    public function getFacebook(): ?string
    {
        return $this->facebook;
    }

    public function setFacebook(?string $facebook): static
    {
        $this->facebook = self::blankToNull($facebook);

        return $this;
    }

    public function getLinkedin(): ?string
    {
        return $this->linkedin;
    }

    public function setLinkedin(?string $linkedin): static
    {
        $this->linkedin = self::blankToNull($linkedin);

        return $this;
    }

    public function getInstagram(): ?string
    {
        return $this->instagram;
    }

    public function setInstagram(?string $instagram): static
    {
        $this->instagram = self::blankToNull($instagram);

        return $this;
    }

    public function getYoutube(): ?string
    {
        return $this->youtube;
    }

    public function setYoutube(?string $youtube): static
    {
        $this->youtube = self::blankToNull($youtube);

        return $this;
    }

    private static function blankToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function normalizeCoordinate(float|int|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim(str_replace(',', '.', $value));
            if ($value === '') {
                return null;
            }
        }

        if (!is_numeric($value)) {
            return (string) $value;
        }

        return number_format((float) $value, 6, '.', '');
    }

    private function coordinate(?string $value): ?float
    {
        if ($value === null || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private static function formatDegree(float $value): string
    {
        return number_format($value, 6, '.', '');
    }

    /** @return array{0: float, 1: float, 2: float, 3: float} */
    private function boundingBox(float $lat, float $lon, int $zoom, int $width, int $height): array
    {
        $world = 256 * (2 ** $zoom);
        $x = ($lon + 180) / 360 * $world;
        $clamped = max(-85.0, min(85.0, $lat));
        $sin = sin(deg2rad($clamped));
        $y = (0.5 - log((1 + $sin) / (1 - $sin)) / (4 * M_PI)) * $world;

        $halfWidth = $width / 2;
        $halfHeight = $height / 2;
        $minLon = (($x - $halfWidth) / $world) * 360 - 180;
        $maxLon = (($x + $halfWidth) / $world) * 360 - 180;
        $minLat = rad2deg(atan(sinh(M_PI - 2 * M_PI * ($y + $halfHeight) / $world)));
        $maxLat = rad2deg(atan(sinh(M_PI - 2 * M_PI * ($y - $halfHeight) / $world)));

        return [$minLon, $minLat, $maxLon, $maxLat];
    }

    /** @return array<string, string> */
    public function getSocialLinks(): array
    {
        $links = [
            'Facebook' => $this->facebook,
            'LinkedIn' => $this->linkedin,
            'Instagram' => $this->instagram,
            'YouTube' => $this->youtube,
        ];

        return array_filter($links, static fn (?string $url): bool => $url !== null && trim($url) !== '');
    }

    public function getFooterText(): ?string
    {
        return $this->footerText;
    }

    public function setFooterText(?string $footerText): static
    {
        $this->footerText = $footerText;

        return $this;
    }

    public function getCopyright(): ?string
    {
        return $this->copyright;
    }

    public function setCopyright(?string $copyright): static
    {
        $this->copyright = $copyright;

        return $this;
    }

    public function getHeaderCtaLabel(): ?string
    {
        return $this->headerCtaLabel;
    }

    public function setHeaderCtaLabel(?string $headerCtaLabel): static
    {
        $this->headerCtaLabel = $headerCtaLabel;

        return $this;
    }

    public function getHeaderCtaUrl(): ?string
    {
        return $this->headerCtaUrl;
    }

    public function setHeaderCtaUrl(?string $headerCtaUrl): static
    {
        $this->headerCtaUrl = $headerCtaUrl;

        return $this;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): static
    {
        $this->metaTitle = $metaTitle;

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): static
    {
        $this->metaDescription = $metaDescription;

        return $this;
    }

    public function __toString(): string
    {
        return 'Paramètres du site';
    }
}
