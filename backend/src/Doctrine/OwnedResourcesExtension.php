<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Folder;
use App\Entity\ShareLink;
use App\Entity\StoredFile;
use Rocket\Core\Security\ActorContext;
use Doctrine\ORM\QueryBuilder;

/** A space is private: everyone, administrators included, only lists their own folders, files and share links. */
final class OwnedResourcesExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private readonly ActorContext $actor)
    {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (!\in_array($resourceClass, [Folder::class, StoredFile::class, ShareLink::class], true)) {
            return;
        }
        $alias = $queryBuilder->getRootAliases()[0];
        $user = $this->actor->getUser();
        if (null === $user) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }
        $param = $queryNameGenerator->generateParameterName('owner');
        $queryBuilder->andWhere(\sprintf('%s.owner = :%s', $alias, $param))->setParameter($param, $user->getId(), 'uuid');
    }
}
