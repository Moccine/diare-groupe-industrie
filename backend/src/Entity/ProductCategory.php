<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\CategoryAccent;
use App\Enum\CategoryIcon;
use App\Repository\ProductCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductCategoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'uniq_product_category_slug', columns: ['slug'])]
class ProductCategory
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/')]
    private string $slug = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20, enumType: CategoryIcon::class)]
    private CategoryIcon $icon = CategoryIcon::Generic;

    #[ORM\Column(length: 20, enumType: CategoryAccent::class)]
    private CategoryAccent $accentColor = CategoryAccent::Forest;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $isPublished = true;

    /** @var Collection<int, Product> */
    #[ORM\OneToMany(mappedBy: 'category', targetEntity: Product::class)]
    private Collection $products;

    public function __construct()
    {
        $this->initializeTimestamps();
        $this->products = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getIcon(): CategoryIcon
    {
        return $this->icon;
    }

    public function setIcon(CategoryIcon $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getIconKey(): string
    {
        return $this->icon->value;
    }

    public function getAccentColor(): CategoryAccent
    {
        return $this->accentColor;
    }

    public function setAccentColor(CategoryAccent $accentColor): static
    {
        $this->accentColor = $accentColor;

        return $this;
    }

    public function getAccentCss(): string
    {
        return $this->accentColor->cssColor();
    }

    public function getAccentInk(): string
    {
        return $this->accentColor->ink();
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

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;

        return $this;
    }

    /** @return Collection<int, Product> */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function __toString(): string
    {
        return $this->name !== '' ? $this->name : 'Catégorie';
    }
}
