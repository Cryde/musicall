<?php declare(strict_types=1);

namespace App\Extensions\Doctrine\Musician;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Musician\MusicianAnnounce;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class MusicianAnnounceSelfExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function applyToCollection(
        QueryBuilder                $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string                      $resourceClass,
        ?Operation                   $operation = null,
        array                       $context = []
    ): void {
        if (MusicianAnnounce::class !== $resourceClass
            || ($operation && $operation->getName() !== 'api_musician_announces_get_self_collection')) {
            return;
        }
        /** @var User $user */
        $user = $this->security->getUser();
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere(sprintf('%s.author = :author', $rootAlias));
        $queryBuilder->setParameter('author', $user);
    }
}
