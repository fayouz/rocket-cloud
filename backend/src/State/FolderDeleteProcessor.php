<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Folder;
use App\Files\FileStorage;
use App\Repository\StoredFileRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Deletes a folder with everything under it: the database cascades, the contents are removed from the disk.
 *
 * @implements ProcessorInterface<Folder, null>
 */
final class FolderDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly StoredFileRepository $files,
        private readonly FileStorage $storage,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Folder) {
            $contents = $this->files->findUnder($data);
            $this->em->remove($data);
            $this->em->flush();
            foreach ($contents as $file) {
                $this->storage->delete($file);
            }
            $this->em->clear();
        }

        return null;
    }
}
