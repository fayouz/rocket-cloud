<?php

namespace App\Controller;

use App\Entity\ShareLink;
use App\Entity\StoredFile;
use App\Files\FileStorage;
use App\Repository\ShareLinkRepository;
use App\Repository\StoredFileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The public side of share links (no account): what is shared, and the download. Downloads are POSTed so the
 * password never ends up in a URL (logs, history).
 */
final class PublicShareController extends AbstractController
{
    public function __construct(
        private readonly ShareLinkRepository $shares,
        private readonly ClockInterface $clock,
        private readonly PasswordHasherFactoryInterface $hashers,
    ) {
    }

    #[Route('/api/public/shares/{token}', name: 'api_public_share', methods: ['GET'])]
    public function show(string $token, StoredFileRepository $files): JsonResponse
    {
        $share = $this->find($token);
        if (null !== $reason = $share->unavailableReason($this->clock->now())) {
            return $this->json(['detail' => $reason], Response::HTTP_GONE);
        }
        $owner = $share->getFile()?->getOwner() ?? $share->getFolder()?->getOwner();
        $data = [
            'name' => $share->getTargetName(),
            'type' => null !== $share->getFile() ? 'file' : 'folder',
            'sharedBy' => $owner?->getDisplayName(),
            'message' => $share->getMessage(),
            'expiresAt' => $share->getExpiresAt()?->format(\DATE_ATOM),
            'passwordProtected' => $share->isPasswordProtected(),
            'remainingDownloads' => null === $share->getMaxDownloads() ? null : $share->getMaxDownloads() - $share->getDownloadCount(),
        ];
        // The content is only described once the password is known (see download()).
        if (!$share->isPasswordProtected()) {
            $data['files'] = array_map(static fn (StoredFile $f) => self::describe($f), null !== $share->getFile() ? [$share->getFile()] : $files->findUnder($share->getFolder()));
        }

        return $this->json($data);
    }

    /**
     * Form fields: "password" (protected links), "file" (a file of a shared folder), "list" (1: returns the
     * folder content as JSON instead of a file, once the password is checked).
     */
    #[Route('/api/public/shares/{token}/download', name: 'api_public_share_download', methods: ['POST'])]
    public function download(string $token, Request $request, StoredFileRepository $files, FileStorage $storage, EntityManagerInterface $em): Response
    {
        $share = $this->find($token);
        $now = $this->clock->now();
        if (null !== $reason = $share->unavailableReason($now)) {
            return $this->json(['detail' => $reason], Response::HTTP_GONE);
        }
        if ($share->isPasswordProtected() && !$this->hashers->getPasswordHasher('share')->verify((string) $share->getPasswordHash(), $request->request->getString('password'))) {
            return $this->json(['detail' => 'Mot de passe incorrect.'], Response::HTTP_FORBIDDEN);
        }

        $available = null !== $share->getFile() ? [$share->getFile()] : $files->findUnder($share->getFolder());
        if ($request->request->getBoolean('list')) {
            return $this->json(['files' => array_map(static fn (StoredFile $f) => self::describe($f), $available)]);
        }
        $fileId = $request->request->getString('file');
        $file = '' === $fileId ? ($available[0] ?? null) : (array_values(array_filter($available, static fn (StoredFile $f) => (string) $f->getId() === $fileId))[0] ?? null);
        if (null === $file || ('' === $fileId && null === $share->getFile())) {
            throw $this->createNotFoundException('No such file in this share.');
        }

        $share->recordDownload($now);
        $em->flush();

        return FileController::send($file, $storage, true);
    }

    private function find(string $token): ShareLink
    {
        return $this->shares->findOneBy(['token' => $token]) ?? throw $this->createNotFoundException('Ce lien n’existe pas ou a été supprimé.');
    }

    /** @return array{id: string, name: string, size: int, mimeType: string} */
    private static function describe(StoredFile $file): array
    {
        return ['id' => (string) $file->getId(), 'name' => $file->getName(), 'size' => $file->getSize(), 'mimeType' => $file->getMimeType()];
    }
}
