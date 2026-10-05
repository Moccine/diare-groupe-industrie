<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\SectionTheme;
use App\Enum\SectionType;
use App\Repository\SectionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SectionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Section
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'sections')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Page $page = null;

    #[ORM\Column(length: 40, enumType: SectionType::class)]
    private SectionType $type = SectionType::Custom;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subtitle = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $eyebrow = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Media $image = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Media $backgroundImage = null;

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

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $isVisible = true;

    #[ORM\Column(length: 40, enumType: SectionTheme::class)]
    private SectionTheme $theme = SectionTheme::Default;

    /** @var Collection<int, SectionItem> */
    #[ORM\OneToMany(mappedBy: 'section', targetEntity: SectionItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $items;

    /** @var Collection<int, HeroSlide> */
    #[ORM\OneToMany(mappedBy: 'section', targetEntity: HeroSlide::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $slides;

    public function __construct()
    {
        $this->initializeTimestamps();
        $this->items = new ArrayCollection();
        $this->slides = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPage(): ?Page
    {
        return $this->page;
    }

    public function setPage(?Page $page): static
    {
        $this->page = $page;

        return $this;
    }

    public function getType(): SectionType
    {
        return $this->type;
    }

    public function setType(SectionType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): static
    {
        $this->subtitle = $subtitle;

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

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

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

    public function getBackgroundImage(): ?Media
    {
        return $this->backgroundImage;
    }

    public function setBackgroundImage(?Media $backgroundImage): static
    {
        $this->backgroundImage = $backgroundImage;

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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function isVisible(): bool
    {
        return $this->isVisible;
    }

    public function setIsVisible(bool $isVisible): static
    {
        $this->isVisible = $isVisible;

        return $this;
    }

    public function getTheme(): SectionTheme
    {
        return $this->theme;
    }

    public function setTheme(SectionTheme $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    /** @return Collection<int, SectionItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(SectionItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setSection($this);
        }

        return $this;
    }

    public function removeItem(SectionItem $item): static
    {
        if ($this->items->removeElement($item) && $item->getSection() === $this) {
            $item->setSection(null);
        }

        return $this;
    }

    /** @return Collection<int, HeroSlide> */
    public function getSlides(): Collection
    {
        return $this->slides;
    }

    public function addSlide(HeroSlide $slide): static
    {
        if (!$this->slides->contains($slide)) {
            $this->slides->add($slide);
            $slide->setSection($this);
        }

        return $this;
    }

    public function removeSlide(HeroSlide $slide): static
    {
        if ($this->slides->removeElement($slide) && $slide->getSection() === $this) {
            $slide->setSection(null);
        }

        return $this;
    }

    /** @return list<HeroSlide> */
    public function getActiveSlides(): array
    {
        return $this->slides->filter(
            static fn (HeroSlide $slide): bool => $slide->isActive() && $slide->getImage() !== null
        )->getValues();
    }

    public function __toString(): string
    {
        $title = trim((string) $this->title);

        return $title !== '' ? $title : $this->type->label();
    }
}
