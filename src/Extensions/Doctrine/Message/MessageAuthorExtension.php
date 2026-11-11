<?php declare(strict_types=1);

namespace App\Extensions\Doctrine\Message;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Message\Message;
use App\Repository\Message\MessageRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * Loads what each bubble of a conversation page shows with the page, see
 * MessageRepository::joinConversationPage().
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

        MessageRepository::joinConversationPage($queryBuilder, $queryBuilder->getRootAliases()[0]);
    }
}
