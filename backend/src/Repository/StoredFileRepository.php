<?php

namespace App\Repository;

use App\Entity\StoredFile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<StoredFile> */
class StoredFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StoredFile::class);
    }

    /** Bytes used by a user. */
    public function usage(\App\Entity\User $owner): int
    {
        return (int) $this->createQueryBuilder('f')->select('COALESCE(SUM(f.size), 0)')->where('f.owner = :owner')
            ->setParameter('owner', $owner->getId(), 'uuid')->getQuery()->getSingleScalarResult();
    }

    /**
     * Every file under a folder, at any depth.
     *
     * @return list<\App\Entity\StoredFile>
     */
    public function findUnder(\App\Entity\Folder $folder): array
    {
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'WITH RECURSIVE tree AS (SELECT id FROM folder WHERE id = :root UNION ALL SELECT f.id FROM folder f JOIN tree t ON f.parent_id = t.id)
             SELECT s.id FROM stored_file s WHERE s.folder_id IN (SELECT id FROM tree) LIMIT 10000',
            ['root' => $folder->getId()->toRfc4122()],
        );

        return [] === $ids ? [] : $this->findBy(['id' => $ids], ['name' => 'ASC']);
    }
}
