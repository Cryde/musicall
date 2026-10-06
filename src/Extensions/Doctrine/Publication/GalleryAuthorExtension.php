<?php declare(strict_types=1);

namespace App\Extensions\Doctrine\Publication;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Gallery;
use Doctrine\ORM\QueryBuilder;

/**
 * Loads each gallery's author and their profile with the list, since every card names its author
 * (#1118). Left lazy, that is two queries per gallery.
 */
class GalleryAuthorExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if ($resourceClass !== Gallery::class) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $authorAlias = $queryNameGenerator->generateJoinAlias('author');
        $profileAlias = $queryNameGenerator->generateJoinAlias('author_profile');
        $queryBuilder
            ->innerJoin(sprintf('%s.author', $rootAlias), $authorAlias)
            ->innerJoin(sprintf('%s.profile', $authorAlias), $profileAlias)
            ->addSelect($authorAlias, $profileAlias);
    }
}
