<?php

declare(strict_types=1);

namespace App\State\Provider\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Publication\PublicationListItem;
use App\Repository\PublicationRepository;
use App\Service\Builder\Publication\PublicationListItemBuilder;
use App\Service\Metric\PublicationUserVoteResolver;

/**
 * @implements ProviderInterface<PublicationListItem>
 */
readonly class LastPublicationsProvider implements ProviderInterface
{
    private const LAST_PUBLICATIONS_LIMIT = 4;

    public function __construct(
        private PublicationRepository       $publicationRepository,
        private PublicationListItemBuilder  $publicationListItemBuilder,
        private PublicationUserVoteResolver $userVoteResolver,
    ) {
    }

    /**
     * @return PublicationListItem[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $publications = $this->publicationRepository->findLastPublications(self::LAST_PUBLICATIONS_LIMIT);

        return $this->publicationListItemBuilder->buildList(
            $publications,
            $this->userVoteResolver->resolveForPublications($publications),
        );
    }
}
