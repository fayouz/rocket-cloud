<?php

namespace App\Entity;

use Rocket\Core\Entity\User;
use Rocket\Core\Entity\TrackedTrait;
use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\FolderRepository;
use App\State\FolderDeleteProcessor;
use App\State\OwnedProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** A folder of a user's space. Deleting it deletes its content. */
#[ORM\Entity(repositoryClass: FolderRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_folder_name', columns: ['owner_id', 'parent_id', 'name'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(security: 'object.getOwner() == user'),
        new Post(processor: OwnedProcessor::class),
        new Patch(security: 'object.getOwner() == user'),
        new Delete(security: 'object.getOwner() == user', processor: FolderDeleteProcessor::class),
    ],
    normalizationContext: ['groups' => ['folder:read', 'tracking']],
    denormalizationContext: ['groups' => ['folder:write']],
    order: ['name' => 'ASC'],
    paginationClientItemsPerPage: true,
)]
#[ApiFilter(SearchFilter::class, properties: ['parent' => 'exact', 'name' => 'ipartial'])]
#[ApiFilter(ExistsFilter::class, properties: ['parent'])]
class Folder
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['folder:read', 'file:read'])]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(pattern: '#^[^/\\\\\x00]+$#', message: 'A name cannot contain "/" or "\\".')]
    #[Groups(['folder:read', 'folder:write', 'file:read'])]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups(['folder:read', 'folder:write'])]
    private ?self $parent = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    use TrackedTrait;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    #[Assert\Callback]
    public function validateParent(ExecutionContextInterface $context): void
    {
        for ($folder = $this->parent, $depth = 0; null !== $folder; $folder = $folder->getParent(), ++$depth) {
            if ($folder === $this || $depth > 50) {
                $context->buildViolation('A folder cannot be moved into itself.')->atPath('parent')->addViolation();

                return;
            }
        }
        if (null !== $this->parent && null !== $this->owner && $this->parent->getOwner() !== $this->owner) {
            $context->buildViolation('This folder does not exist.')->atPath('parent')->addViolation();
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

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * From the root to this folder, for breadcrumbs.
     *
     * @return list<array{id: string, name: string}>
     */
    #[Groups(['folder:read'])]
    public function getPath(): array
    {
        $path = [];
        for ($folder = $this; null !== $folder && \count($path) < 50; $folder = $folder->getParent()) {
            array_unshift($path, ['id' => (string) $folder->getId(), 'name' => $folder->getName()]);
        }

        return $path;
    }
}
