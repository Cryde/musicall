<?php declare(strict_types=1);

namespace App\Extensions\Doctrine\Message;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Message\Message;
use Doctrine\ORM\QueryBuilder;

/**
 * Loads each message's author and their profile with the page, since every bubble can name its
 * author (#1118). Left lazy, that is two queries per distinct author.
 */
class MessageAuthorExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if ($resourceClass !== Message::class) {
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
