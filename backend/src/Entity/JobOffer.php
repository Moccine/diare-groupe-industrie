<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\ContractType;
use App\Repository\JobOfferRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: JobOfferRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'uniq_job_offer_slug', columns: ['slug'])]
class JobOffer
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $title = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/')]
    private string $slug = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $department = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(length: 20, enumType: ContractType::class)]
    private ContractType $contractType = ContractType::Other;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $shortDescription = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $requirements = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    private ?string $applicationEmail = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url]
    private ?string $applicationUrl = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $experienceLevel = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $salaryLabel = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $publishedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column]
    private bool $isPublished = false;

    public function __construct()
    {
        $this->initializeTimestamps();
        $this->publishedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(?string $department): static
    {
        $this->department = self::blankToNull($department);

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = self::blankToNull($location);

        return $this;
    }

    public function getContractType(): ContractType
    {
        return $this->contractType;
    }

    public function setContractType(ContractType $contractType): static
    {
        $this->contractType = $contractType;

        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): static
    {
        $this->shortDescription = self::blankToNull($shortDescription);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = self::blankToNull($description);

        return $this;
    }

    public function getRequirements(): ?string
    {
        return $this->requirements;
    }

    public function setRequirements(?string $requirements): static
    {
        $this->requirements = self::blankToNull($requirements);

        return $this;
    }

    public function getApplicationEmail(): ?string
    {
        return $this->applicationEmail;
    }

    public function setApplicationEmail(?string $applicationEmail): static
    {
        $this->applicationEmail = self::blankToNull($applicationEmail);

        return $this;
    }

    public function getApplicationUrl(): ?string
    {
        return $this->applicationUrl;
    }

    public function setApplicationUrl(?string $applicationUrl): static
    {
        $this->applicationUrl = self::blankToNull($applicationUrl);

        return $this;
    }

    public function getExperienceLevel(): ?string
    {
        return $this->experienceLevel;
    }

    public function setExperienceLevel(?string $experienceLevel): static
    {
        $this->experienceLevel = self::blankToNull($experienceLevel);

        return $this;
    }

    public function getSalaryLabel(): ?string
    {
        return $this->salaryLabel;
    }

    public function setSalaryLabel(?string $salaryLabel): static
    {
        $this->salaryLabel = self::blankToNull($salaryLabel);

        return $this;
    }

    public function getPublishedAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;

        return $this;
    }

    public function isOpenAt(\DateTimeImmutable $now): bool
    {
        if (!$this->isPublished || $this->publishedAt > $now) {
            return false;
        }

        return $this->expiresAt === null || $this->expiresAt > $now;
    }

    public function isExternalApplication(): bool
    {
        return $this->applicationUrl !== null && trim($this->applicationUrl) !== '';
    }

    public function getApplicationHref(): ?string
    {
        if ($this->isExternalApplication()) {
            return $this->applicationUrl;
        }

        $email = trim((string) $this->applicationEmail);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return 'mailto:'.$email.'?subject='.rawurlencode('Candidature : '.$this->title);
    }

    private static function blankToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    public function __toString(): string
    {
        return $this->title !== '' ? $this->title : 'Offre d’emploi';
    }
}
