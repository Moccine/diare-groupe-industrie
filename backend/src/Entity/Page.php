<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Repository\PageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PageRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'uniq_page_slug', columns: ['slug'])]
class Page
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $title = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Le slug ne peut contenir que des minuscules, des chiffres et des tirets.')]
    private string $slug = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $menuTitle = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column]
    private bool $isPublished = true;

    #[ORM\Column]
    private bool $showInMenu = false;

    #[ORM\Column]
    private int $menuPosition = 0;

    /** @var Collection<int, Section> */
    #[ORM\OneToMany(mappedBy: 'page', targetEntity: Section::class, cascade: ['persist'], orphanRemoval: false)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $sections;

    public function __construct()
    {
        $this->initializeTimestamps();
        $this->sections = new ArrayCollection();
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

    public function getMenuTitle(): ?string
    {
        return $this->menuTitle;
    }

    public function setMenuTitle(?string $menuTitle): static
    {
        $this->menuTitle = $menuTitle;

        return $this;
    }

    public function getMenuLabel(): string
    {
        $label = trim((string) $this->menuTitle);

        return $label !== '' ? $label : $this->title;
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

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;

        return $this;
    }

    public function isShowInMenu(): bool
    {
        return $this->showInMenu;
    }

    public function setShowInMenu(bool $showInMenu): static
    {
        $this->showInMenu = $showInMenu;

        return $this;
    }

    public function getMenuPosition(): int
    {
        return $this->menuPosition;
    }

    public function setMenuPosition(int $menuPosition): static
    {
        $this->menuPosition = $menuPosition;

        return $this;
    }

    /** @return Collection<int, Section> */
    public function getSections(): Collection
    {
        return $this->sections;
    }

    public function addSection(Section $section): static
    {
        if (!$this->sections->contains($section)) {
            $this->sections->add($section);
            $section->setPage($this);
        }

        return $this;
    }

    public function removeSection(Section $section): static
    {
        $this->sections->removeElement($section);

        return $this;
    }

    public function getPublicPath(): string
    {
        return $this->slug === 'accueil' ? '/' : '/'.$this->slug;
    }

    public function __toString(): string
    {
        return $this->title !== '' ? $this->title : 'Page';
    }
}
