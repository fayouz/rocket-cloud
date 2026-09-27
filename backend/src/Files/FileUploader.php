<?php

namespace App\Files;

use App\Entity\Folder;
use App\Entity\StoredFile;
use Rocket\Core\Entity\User;
use App\Repository\StoredFileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Mime\MimeTypes;

/** Stores an uploaded file in a user's space, within their quota and the size limit. */
class FileUploader
{
    public function __construct(
        private readonly FileStorage $storage,
        private readonly StoredFileRepository $files,
        private readonly EntityManagerInterface $em,
        #[Autowire(env: 'int:CLOUD_MAX_FILE_SIZE')] private readonly int $maxFileSize,
        #[Autowire(env: 'int:CLOUD_DEFAULT_QUOTA')] private readonly int $quota,
    ) {
    }

    public function quota(): int
    {
        return $this->quota;
    }

    public function maxFileSize(): int
    {
        return $this->maxFileSize;
    }

    public function upload(UploadedFile $upload, User $owner, ?Folder $folder, ?string $name = null): StoredFile
    {
        if (!$upload->isValid()) {
            throw new UnprocessableEntityHttpException($upload->getErrorMessage());
        }
        $size = (int) $upload->getSize();
        if ($size > $this->maxFileSize) {
            throw new HttpException(413, \sprintf('The file exceeds the maximum size (%d bytes).', $this->maxFileSize));
        }
        if ($this->files->usage($owner) + $size > $this->quota) {
            throw new HttpException(413, 'Not enough space left in your storage quota.');
        }
        if (null !== $folder && $folder->getOwner() !== $owner) {
            throw new UnprocessableEntityHttpException('This folder does not exist.');
        }

        $name = self::sanitizeName($name ?? $upload->getClientOriginalName());
        $mime = MimeTypes::getDefault()->guessMimeType($upload->getPathname()) ?? 'application/octet-stream';
        $file = (new StoredFile($owner, $name, $size, $mime, (string) hash_file('sha256', $upload->getPathname())))->setFolder($folder);

        $this->storage->store($file, $upload);
        $this->em->persist($file);
        $this->em->flush();

        return $file;
    }

    /**
     * Replaces the content of a file (same id, name, folder and share links), within the quota and the size limit.
     * The new type is detected from the content.
     */
    public function replace(StoredFile $file, File $content): StoredFile
    {
        $size = (int) $content->getSize();
        if ($size > $this->maxFileSize) {
            throw new HttpException(413, \sprintf('The file exceeds the maximum size (%d bytes).', $this->maxFileSize));
        }
        if ($this->files->usage($file->getOwner()) - $file->getSize() + $size > $this->quota) {
            throw new HttpException(413, 'Not enough space left in your storage quota.');
        }
        $mime = MimeTypes::getDefault()->guessMimeType($content->getPathname()) ?? 'application/octet-stream';
        $sha256 = (string) hash_file('sha256', $content->getPathname());

        $this->storage->store($file, $content);
        $file->replaceContent($size, $mime, $sha256);
        $this->em->flush();

        return $file;
    }

    /** A single path segment, without control characters. */
    public static function sanitizeName(string $name): string
    {
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '_', $name), " .\t");

        return '' === $name ? 'fichier' : mb_substr($name, 0, 255);
    }
}
