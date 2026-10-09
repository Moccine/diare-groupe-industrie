<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\JobApplicationStatus;
use App\Repository\JobApplicationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: JobApplicationRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_job_application_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_job_application_status', columns: ['status'])]
#[ORM\Index(name: 'idx_job_application_job_offer', columns: ['job_offer_id'])]
class JobApplication
{
    use TimestampableTrait;

    public const CONSENT_VERSION = 'candidature-v1';

    public const MAX_CV_BYTES = 5242880;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?JobOffer $jobOffer = null;

    #[ORM\Column(length: 180)]
    private string $jobTitleSnapshot = '';

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $contractTypeSnapshot = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $locationSnapshot = null;

    #[ORM\Column]
    private bool $isSpontaneous = false;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $desiredRole = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 100)]
    private string $firstName = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 100)]
    private string $lastName = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private string $email = '';

    #[ORM\Column(length: 40)]
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 40)]
    private string $phone = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 2000)]
    private string $motivation = '';

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $linkedinUrl = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $availability = null;

    #[ORM\Column(length: 80)]
    private string $cvStoredFilename = '';

    #[ORM\Column(length: 180)]
    private string $cvOriginalFilename = '';

    #[ORM\Column(length: 80)]
    private string $cvMimeType = '';

    #[ORM\Column]
    private int $cvSize = 0;

    #[ORM\Column(length: 20, enumType: JobApplicationStatus::class)]
    private JobApplicationStatus $status = JobApplicationStatus::New;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $internalNotes = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $consentedAt;

    #[ORM\Column(length: 40)]
    private string $consentVersion = self::CONSENT_VERSION;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $retentionUntil = null;

    public function __construct()
    {
        $this->initializeTimestamps();
        $this->consentedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJobOffer(): ?JobOffer
    {
        return $this->jobOffer;
    }

    public function setJobOffer(?JobOffer $jobOffer): static
    {
        $this->jobOffer = $jobOffer;

        return $this;
    }

    public function getJobTitleSnapshot(): string
    {
        return $this->jobTitleSnapshot;
    }

    public function setJobTitleSnapshot(string $jobTitleSnapshot): static
    {
        $this->jobTitleSnapshot = trim($jobTitleSnapshot);

        return $this;
    }

    public function getContractTypeSnapshot(): ?string
    {
        return $this->contractTypeSnapshot;
    }

    public function setContractTypeSnapshot(?string $contractTypeSnapshot): static
    {
        $this->contractTypeSnapshot = self::blankToNull($contractTypeSnapshot);

        return $this;
    }

    public function getLocationSnapshot(): ?string
    {
        return $this->locationSnapshot;
    }

    public function setLocationSnapshot(?string $locationSnapshot): static
    {
        $this->locationSnapshot = self::blankToNull($locationSnapshot);

        return $this;
    }

    public function isSpontaneous(): bool
    {
        return $this->isSpontaneous;
    }

    public function setIsSpontaneous(bool $isSpontaneous): static
    {
        $this->isSpontaneous = $isSpontaneous;

        return $this;
    }

    public function getDesiredRole(): ?string
    {
        return $this->desiredRole;
    }

    public function setDesiredRole(?string $desiredRole): static
    {
        $this->desiredRole = self::blankToNull($desiredRole);

        return $this;
    }

    public function captureContext(?JobOffer $offer): void
    {
        if ($offer !== null) {
            $this->jobOffer = $offer;
            $this->isSpontaneous = false;
            $this->jobTitleSnapshot = $offer->getTitle();
            $this->contractTypeSnapshot = $offer->getContractType()->label();
            $this->locationSnapshot = $offer->getLocation();
            $this->desiredRole = null;

            return;
        }

        $this->jobOffer = null;
        $this->isSpontaneous = true;
        $this->contractTypeSnapshot = null;
        $this->locationSnapshot = null;
        $role = trim((string) $this->desiredRole);
        $this->jobTitleSnapshot = $role !== '' ? $role : 'Candidature spontanée';
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = self::collapseSpaces($firstName);

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = self::collapseSpaces($lastName);

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = trim($email);

        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = trim($phone);

        return $this;
    }

    public function getMotivation(): string
    {
        return $this->motivation;
    }

    public function setMotivation(string $motivation): static
    {
        $clean = str_replace("\0", '', strip_tags($motivation));
        $this->motivation = trim($clean);

        return $this;
    }

    public function getLinkedinUrl(): ?string
    {
        return $this->linkedinUrl;
    }

    public function setLinkedinUrl(?string $linkedinUrl): static
    {
        $this->linkedinUrl = self::blankToNull($linkedinUrl);

        return $this;
    }

    public function getAvailability(): ?string
    {
        return $this->availability;
    }

    public function setAvailability(?string $availability): static
    {
        $this->availability = self::blankToNull($availability);

        return $this;
    }

    public function getCvStoredFilename(): string
    {
        return $this->cvStoredFilename;
    }

    public function getCvOriginalFilename(): string
    {
        return $this->cvOriginalFilename;
    }

    public function getCvMimeType(): string
    {
        return $this->cvMimeType;
    }

    public function getCvSize(): int
    {
        return $this->cvSize;
    }

    public function isCvAvailable(): bool
    {
        return $this->cvStoredFilename !== '';
    }

    public function getCvSizeLabel(): string
    {
        if ($this->cvSize < 1024) {
            return $this->cvSize.' o';
        }

        if ($this->cvSize < 1048576) {
            return number_format($this->cvSize / 1024, 1, ',', ' ').' Ko';
        }

        return number_format($this->cvSize / 1048576, 1, ',', ' ').' Mo';
    }

    public function attachCv(string $storedFilename, string $originalFilename, string $mimeType, int $size): void
    {
        $this->cvStoredFilename = $storedFilename;
        $this->cvOriginalFilename = $originalFilename;
        $this->cvMimeType = $mimeType;
        $this->cvSize = $size;
    }

    public function getStatus(): JobApplicationStatus
    {
        return $this->status;
    }

    public function setStatus(JobApplicationStatus $status): static
    {
        $this->status = $status;
        if ($status->isFinal() && $this->processedAt === null) {
            $this->processedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getStatusLabel(): string
    {
        return $this->status->label();
    }

    public function getInternalNotes(): ?string
    {
        return $this->internalNotes;
    }

    public function setInternalNotes(?string $internalNotes): static
    {
        $this->internalNotes = self::blankToNull($internalNotes);

        return $this;
    }

    public function getConsentedAt(): \DateTimeImmutable
    {
        return $this->consentedAt;
    }

    public function getConsentVersion(): string
    {
        return $this->consentVersion;
    }

    public function recordConsent(\DateTimeImmutable $at): void
    {
        $this->consentedAt = $at;
        $this->consentVersion = self::CONSENT_VERSION;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeImmutable $processedAt): static
    {
        $this->processedAt = $processedAt;

        return $this;
    }

    public function getRetentionUntil(): ?\DateTimeImmutable
    {
        return $this->retentionUntil;
    }

    public function setRetentionUntil(?\DateTimeImmutable $retentionUntil): static
    {
        $this->retentionUntil = $retentionUntil;

        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName.' '.$this->lastName);
    }

    public function getPositionLabel(): string
    {
        if ($this->isSpontaneous) {
            return $this->desiredRole ?? $this->jobTitleSnapshot;
        }

        if ($this->jobOffer !== null) {
            $current = $this->jobOffer->getTitle();
            if ($current !== $this->jobTitleSnapshot && $this->jobTitleSnapshot !== '') {
                return $current;
            }

            return $current !== '' ? $current : $this->jobTitleSnapshot;
        }

        if ($this->jobTitleSnapshot === '') {
            return 'Offre retirée';
        }

        return $this->jobTitleSnapshot.' (offre retirée)';
    }

    #[Assert\Callback]
    public function validateContent(ExecutionContextInterface $context): void
    {
        $this->validatePhone($context);
        $this->validateMotivation($context);
        $this->validateLinkedin($context);
    }

    public function __toString(): string
    {
        $name = $this->getFullName();

        return $name !== '' ? $name : 'Candidature';
    }

    private function validatePhone(ExecutionContextInterface $context): void
    {
        if ($this->phone === '') {
            return;
        }

        if (!preg_match('/^[+0-9][0-9\s().\/-]{6,39}$/', $this->phone)) {
            $context->buildViolation('Indiquez un numéro de téléphone valide.')
                ->atPath('phone')
                ->addViolation();

            return;
        }

        $digits = preg_replace('/\D/', '', $this->phone) ?? '';
        $length = strlen($digits);
        if ($length < 8 || $length > 16) {
            $context->buildViolation('Indiquez un numéro de téléphone valide, avec l’indicatif si besoin.')
                ->atPath('phone')
                ->addViolation();
        }
    }

    private function validateMotivation(ExecutionContextInterface $context): void
    {
        if ($this->motivation === '') {
            return;
        }

        $useful = preg_replace('/\s+/u', '', $this->motivation) ?? '';
        if (mb_strlen($useful) < 30) {
            $context->buildViolation('Présentez votre motivation en au moins 30 caractères.')
                ->atPath('motivation')
                ->addViolation();
        }
    }

    private function validateLinkedin(ExecutionContextInterface $context): void
    {
        if ($this->linkedinUrl === null) {
            return;
        }

        if (!preg_match('#^https://(?:[a-z0-9-]+\.)?linkedin\.com/\S+#i', $this->linkedinUrl)) {
            $context->buildViolation('Indiquez une adresse LinkedIn valide, commençant par https://.')
                ->atPath('linkedinUrl')
                ->addViolation();
        }
    }

    private static function collapseSpaces(string $value): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($value));

        return $collapsed ?? trim($value);
    }

    private static function blankToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
