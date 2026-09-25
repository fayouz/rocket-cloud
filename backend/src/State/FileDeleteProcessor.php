<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\StoredFile;
use App\Files\FileStorage;
use Doctrine\ORM\EntityManagerInterface;

/** @implements ProcessorInterface<StoredFile, null> */
final class FileDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FileStorage $storage,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof StoredFile) {
            $this->em->remove($data);
            $this->em->flush();
            $this->storage->delete($data);
        }

        return null;
    }
}
