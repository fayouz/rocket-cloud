<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Repository\StoredFileRepository;
use App\State\FileDeleteProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A stored file. Uploaded with POST /api/files (multipart), read with GET /api/files/{id}/content.
 * The content lives in the storage directory under an opaque key, never under its name.
 */
#[ORM\Entity(repositoryClass: StoredFileRepository::class)]
#[ORM\Index(name: 'idx_stored_file_owner_folder', columns: ['owner_id', 'folder_id'])]
#[ApiResource(
    shortName: 'File',
    operations: [
        new GetCollection(uriTemplate: '/files'),
        new Get(uriTemplate: '/files/{id}', security: 'object.getOwner() == user'),
        new Patch(uriTemplate: '/files/{id}', security: 'object.getOwner() == user'),
        new Delete(uriTemplate: '/files/{id}', security: 'object.getOwner() == user', processor: FileDeleteProcessor::class),
    ],
    normalizationContext: ['groups' => ['file:read', 'tracking']],
    denormalizationContext: ['groups' => ['file:write']],
    order: ['name' => 'ASC'],
    paginationClientItemsPerPage: true,
)]
#[ApiFilter(SearchFilter::class, properties: ['folder' => 'exact', 'name' => 'ipartial', 'mimeType' => 'start'])]
#[ApiFilter(ExistsFilter::class, properties: ['folder'])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'size', 'createdAt', 'updatedAt'])]
class StoredFile
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['file:read'])]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(pattern: '#^[^/\\\\\x00]+$#', message: 'A name cannot contain "/" or "\\".')]
    #[Groups(['file:read', 'file:write'])]
    private string $name = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups(['file:read', 'file:write'])]
    private ?Folder $folder = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(type: 'bigint')]
    #[Groups(['file:read'])]
    private int|string $size = 0;

    #[ORM\Column(length: 127)]
    #[Groups(['file:read'])]
    private string $mimeType = 'application/octet-stream';

    #[ORM\Column(length: 64)]
    #[Groups(['file:read'])]
    private string $sha256 = '';

    #[ORM\Column(length: 80, unique: true)]
    private string $storageKey;

    use TrackedTrait;

    public function __construct(User $owner, string $name, int $size, string $mimeType, string $sha256)
    {
        $this->id = Uuid::v7();
        $this->owner = $owner;
        $this->name = trim($name);
        $this->size = $size;
        $this->mimeType = $mimeType;
        $this->sha256 = $sha256;
        $this->storageKey = substr((string) $this->id, 0, 2).'/'.$this->id;
    }

    #[Assert\Callback]
    public function validateFolder(ExecutionContextInterface $context): void
    {
        if (null !== $this->folder && $this->folder->getOwner() !== $this->owner) {
            $context->buildViolation('This folder does not exist.')->atPath('folder')->addViolation();
        }
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getFolder(): ?Folder
    {
        return $this->folder;
    }

    public function setFolder(?Folder $folder): static
    {
        $this->folder = $folder;

        return $this;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getSize(): int
    {
        return (int) $this->size;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSha256(): string
    {
        return $this->sha256;
    }

    public function getStorageKey(): string
    {
        return $this->storageKey;
    }

    /** Images, PDF, text, audio and video can be previewed by the browser. */
    #[Groups(['file:read'])]
    public function isPreviewable(): bool
    {
        return (bool) preg_match('#^(image/(png|jpeg|gif|webp)|application/pdf|text/plain|audio/|video/)#', $this->mimeType);
    }
}
