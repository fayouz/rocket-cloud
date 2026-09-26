<?php

namespace App\Entity;

use Rocket\Core\Entity\User;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\ShareLinkRepository;
use App\State\ShareLinkCreateProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A public link to a file or a folder, optionally protected by a password, limited in time or in downloads.
 * Recipients can be notified by email through Rocket Mailer.
 */
#[ORM\Entity(repositoryClass: ShareLinkRepository::class)]
#[ApiResource(
    shortName: 'Share',
    operations: [
        new GetCollection(uriTemplate: '/shares'),
        new Get(uriTemplate: '/shares/{id}', security: 'object.getOwner() == user'),
        new Post(uriTemplate: '/shares', processor: ShareLinkCreateProcessor::class),
        new Delete(uriTemplate: '/shares/{id}', security: 'object.getOwner() == user'),
    ],
    normalizationContext: ['groups' => ['share:read']],
    denormalizationContext: ['groups' => ['share:write']],
    order: ['createdAt' => 'DESC'],
    paginationEnabled: false,
)]
#[ApiFilter(SearchFilter::class, properties: ['file' => 'exact', 'folder' => 'exact'])]
class ShareLink
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['share:read'])]
    private Uuid $id;

    /** Random, part of the public URL. */
    #[ORM\Column(length: 64, unique: true)]
    #[Groups(['share:read'])]
    private string $token;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups(['share:read', 'share:write'])]
    private ?StoredFile $file = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups(['share:read', 'share:write'])]
    private ?Folder $folder = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(nullable: true)]
    #[Assert\GreaterThan('now')]
    #[Groups(['share:read', 'share:write'])]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?string $passwordHash = null;

    #[Groups(['share:write'])]
    #[Assert\Length(min: 4, max: 128)]
    private ?string $password = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    #[Groups(['share:read', 'share:write'])]
    private ?int $maxDownloads = null;

    #[ORM\Column]
    #[Groups(['share:read'])]
    private int $downloadCount = 0;

    /** @var list<string> */
    #[ORM\Column(options: ['default' => '[]'])]
    #[Assert\Count(max: 20)]
    #[Assert\All([new Assert\Email()])]
    #[Groups(['share:read', 'share:write'])]
    private array $recipients = [];

    #[ORM\Column(length: 2000, nullable: true)]
    #[Assert\Length(max: 2000)]
    #[Groups(['share:read', 'share:write'])]
    private ?string $message = null;

    /** Email notification: null (none asked), "queued", "sent", or the error. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['share:read'])]
    private ?string $notificationStatus = null;

    #[ORM\Column]
    #[Groups(['share:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['share:read'])]
    private ?\DateTimeImmutable $lastDownloadAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->token = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $this->createdAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateTarget(ExecutionContextInterface $context): void
    {
        if ((null === $this->file) === (null === $this->folder)) {
            $context->buildViolation('Share either a file or a folder.')->atPath('file')->addViolation();
        }
        if (null !== $this->owner && ($this->file?->getOwner() ?? $this->folder?->getOwner() ?? $this->owner) !== $this->owner) {
            $context->buildViolation('This file does not exist.')->atPath(null !== $this->file ? 'file' : 'folder')->addViolation();
        }
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getFile(): ?StoredFile
    {
        return $this->file;
    }

    public function setFile(?StoredFile $file): static
    {
        $this->file = $file;

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

    #[Groups(['share:read'])]
    public function getTargetName(): string
    {
        return $this->file?->getName() ?? $this->folder?->getName() ?? '';
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

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = '' === $password ? null : $password;

        return $this;
    }

    public function setPasswordHash(?string $passwordHash): static
    {
        $this->passwordHash = $passwordHash;

        return $this;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    #[Groups(['share:read'])]
    public function isPasswordProtected(): bool
    {
        return null !== $this->passwordHash;
    }

    public function getMaxDownloads(): ?int
    {
        return $this->maxDownloads;
    }

    public function setMaxDownloads(?int $maxDownloads): static
    {
        $this->maxDownloads = $maxDownloads;

        return $this;
    }

    public function getDownloadCount(): int
    {
        return $this->downloadCount;
    }

    public function recordDownload(\DateTimeImmutable $at): void
    {
        ++$this->downloadCount;
        $this->lastDownloadAt = $at;
    }

    /** @return list<string> */
    public function getRecipients(): array
    {
        return $this->recipients;
    }

    /** @param list<string> $recipients */
    public function setRecipients(array $recipients): static
    {
        $this->recipients = array_values(array_unique(array_filter(array_map(static fn (string $r) => mb_strtolower(trim($r)), $recipients))));

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = null === $message || '' === trim($message) ? null : trim($message);

        return $this;
    }

    public function getNotificationStatus(): ?string
    {
        return $this->notificationStatus;
    }

    public function setNotificationStatus(?string $notificationStatus): static
    {
        $this->notificationStatus = null === $notificationStatus ? null : mb_substr($notificationStatus, 0, 255);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastDownloadAt(): ?\DateTimeImmutable
    {
        return $this->lastDownloadAt;
    }

    /** Why the link cannot be used any more, or null. */
    public function unavailableReason(\DateTimeImmutable $now): ?string
    {
        return match (true) {
            null !== $this->expiresAt && $this->expiresAt <= $now => 'Ce lien a expiré.',
            null !== $this->maxDownloads && $this->downloadCount >= $this->maxDownloads => 'Ce lien a atteint son nombre maximal de téléchargements.',
            !($this->file?->getOwner() ?? $this->folder?->getOwner())?->isEnabled() => 'Ce lien n’est plus disponible.',
            default => null,
        };
    }
}
