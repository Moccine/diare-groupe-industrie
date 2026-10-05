<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'uniq_product_slug', columns: ['slug'])]
class Product
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/')]
    private string $slug = '';

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?ProductCategory $category = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $shortDescription = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Media $mainImage = null;

    /** @var Collection<int, Media> */
    #[ORM\ManyToMany(targetEntity: Media::class)]
    #[ORM\JoinTable(name: 'product_gallery')]
    private Collection $gallery;

    #[ORM\Column]
    private bool $isFeatured = false;

    #[ORM\Column]
    private bool $isPublished = true;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $metaDescription = null;

    public function __construct()
    {
        $this->initializeTimestamps();
        $this->gallery = new ArrayCollection();
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

    public function getCategory(): ?ProductCategory
    {
        return $this->category;
    }

    public function setCategory(?ProductCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): static
    {
        $this->shortDescription = $shortDescription;

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

    public function getMainImage(): ?Media
    {
        return $this->mainImage;
    }

    public function setMainImage(?Media $mainImage): static
    {
        $this->mainImage = $mainImage;

        return $this;
    }

    /** @return Collection<int, Media> */
    public function getGallery(): Collection
    {
        return $this->gallery;
    }

    public function addGallery(Media $media): static
    {
        if (!$this->gallery->contains($media)) {
            $this->gallery->add($media);
        }

        return $this;
    }

    public function removeGallery(Media $media): static
    {
        $this->gallery->removeElement($media);

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): static
    {
        $this->isFeatured = $isFeatured;

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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

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
        return $this->name !== '' ? $this->name : 'Produit';
    }
}
