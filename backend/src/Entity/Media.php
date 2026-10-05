<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Media\MediaImageRules;
use App\Repository\MediaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
class Media
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $fileName = '';

    #[ORM\Column(length: 255)]
    private string $originalName = '';

    #[ORM\Column(length: 255)]
    #[Assert\Length(max: 255)]
    private string $alt = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $caption = null;

    #[ORM\Column(length: 120)]
    private string $mimeType = '';

    #[ORM\Column]
    private int $size = 0;

    #[ORM\Column(nullable: true)]
    private ?int $width = null;

    #[ORM\Column(nullable: true)]
    private ?int $height = null;

    #[Vich\UploadableField(mapping: 'media', fileNameProperty: 'fileName', size: 'size', mimeType: 'mimeType', originalName: 'originalName')]
    #[Assert\File(
        maxSize: '8M',
        mimeTypes: MediaImageRules::ALLOWED_MIME_TYPES,
        mimeTypesMessage: 'Formats acceptés : JPEG, PNG, WebP, GIF, AVIF.',
    )]
    private ?File $imageFile = null;

    private ?string $clientOriginalName = null;

    public function __construct()
    {
        $this->initializeTimestamps();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(?string $fileName): static
    {
        $this->fileName = $fileName ?? '';

        return $this;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(?string $originalName): static
    {
        $this->originalName = $originalName ?? '';

        return $this;
    }

    public function getAlt(): string
    {
        return $this->alt;
    }

    public function setAlt(string $alt): static
    {
        $this->alt = $alt;

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

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(?string $caption): static
    {
        $this->caption = $caption;

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): static
    {
        $this->mimeType = $mimeType ?? '';

        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(?int $size): static
    {
        $this->size = $size ?? 0;

        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageFile(?File $imageFile): void
    {
        $this->imageFile = $imageFile;
        if ($imageFile instanceof UploadedFile) {
            $this->clientOriginalName = $imageFile->getClientOriginalName();
        }

        if ($imageFile !== null) {
            $this->touchUpdatedAt();
        }
    }

    public function rememberOriginalName(string $name): void
    {
        $this->clientOriginalName = $name;
    }

    public function getClientOriginalName(): ?string
    {
        return $this->clientOriginalName;
    }

    public function getPublicPath(): string
    {
        return '/uploads/media/'.$this->fileName;
    }

    public function getThumbnailPath(): string
    {
        if ($this->fileName === '') {
            return '';
        }

        $stem = pathinfo($this->fileName, PATHINFO_FILENAME);

        return $stem !== '' ? '/uploads/media/thumbnails/'.$stem.'.webp' : '';
    }

    public function getDimensionsLabel(): string
    {
        if ($this->width === null || $this->height === null) {
            return '—';
        }

        return $this->width.' × '.$this->height;
    }

    public function getFormattedSize(): string
    {
        if ($this->size < 1024) {
            return $this->size.' o';
        }

        if ($this->size < 1_048_576) {
            return number_format($this->size / 1024, 1, ',', ' ').' Ko';
        }

        return number_format($this->size / 1_048_576, 1, ',', ' ').' Mo';
    }

    public function __toString(): string
    {
        $label = trim($this->title ?? '') !== '' ? (string) $this->title : $this->originalName;

        return $label !== '' ? $label : 'Média';
    }
}
