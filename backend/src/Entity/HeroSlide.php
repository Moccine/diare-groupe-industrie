<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\HeroContentPosition;
use App\Enum\HeroOverlay;
use App\Repository\HeroSlideRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HeroSlideRepository::class)]
#[ORM\HasLifecycleCallbacks]
class HeroSlide
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'slides')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Section $section = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Media $image = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Media $mobileImage = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $eyebrow = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $buttonLabel = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Regex(pattern: '/^(https?:\/\/|\/|#)[^\s]*$/', message: 'Le lien doit commencer par /, # ou http(s)://.')]
    private ?string $buttonUrl = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $secondaryButtonLabel = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Regex(pattern: '/^(https?:\/\/|\/|#)[^\s]*$/', message: 'Le lien doit commencer par /, # ou http(s)://.')]
    private ?string $secondaryButtonUrl = null;

    #[ORM\Column(length: 20, enumType: HeroContentPosition::class)]
    private HeroContentPosition $contentPosition = HeroContentPosition::Start;

    #[ORM\Column(length: 20, enumType: HeroOverlay::class)]
    private HeroOverlay $overlay = HeroOverlay::Medium;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $isActive = true;

    public function __construct()
    {
        $this->initializeTimestamps();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSection(): ?Section
    {
        return $this->section;
    }

    public function setSection(?Section $section): static
    {
        $this->section = $section;

        return $this;
    }

    public function getImage(): ?Media
    {
        return $this->image;
    }

    public function setImage(?Media $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getMobileImage(): ?Media
    {
        return $this->mobileImage;
    }

    public function setMobileImage(?Media $mobileImage): static
    {
        $this->mobileImage = $mobileImage;

        return $this;
    }

    public function getEyebrow(): ?string
    {
        return $this->eyebrow;
    }

    public function setEyebrow(?string $eyebrow): static
    {
        $this->eyebrow = $eyebrow;

        return $this;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getButtonLabel(): ?string
    {
        return $this->buttonLabel;
    }

    public function setButtonLabel(?string $buttonLabel): static
    {
        $this->buttonLabel = $buttonLabel;

        return $this;
    }

    public function getButtonUrl(): ?string
    {
        return $this->buttonUrl;
    }

    public function setButtonUrl(?string $buttonUrl): static
    {
        $this->buttonUrl = $buttonUrl !== null && trim($buttonUrl) === '' ? null : $buttonUrl;

        return $this;
    }

    public function getSecondaryButtonLabel(): ?string
    {
        return $this->secondaryButtonLabel;
    }

    public function setSecondaryButtonLabel(?string $secondaryButtonLabel): static
    {
        $this->secondaryButtonLabel = $secondaryButtonLabel;

        return $this;
    }

    public function getSecondaryButtonUrl(): ?string
    {
        return $this->secondaryButtonUrl;
    }

    public function setSecondaryButtonUrl(?string $secondaryButtonUrl): static
    {
        $this->secondaryButtonUrl = $secondaryButtonUrl !== null && trim($secondaryButtonUrl) === '' ? null : $secondaryButtonUrl;

        return $this;
    }

    public function getContentPosition(): HeroContentPosition
    {
        return $this->contentPosition;
    }

    public function setContentPosition(HeroContentPosition $contentPosition): static
    {
        $this->contentPosition = $contentPosition;

        return $this;
    }

    public function getOverlay(): HeroOverlay
    {
        return $this->overlay;
    }

    public function setOverlay(HeroOverlay $overlay): static
    {
        $this->overlay = $overlay;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function __toString(): string
    {
        return $this->title !== '' ? $this->title : 'Diapositive';
    }
}
