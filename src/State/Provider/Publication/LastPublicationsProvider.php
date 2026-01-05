<?php

declare(strict_types=1);

namespace App\State\Provider\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\PublicationRepository;

/**
 * @implements ProviderInterface<object>
 */
readonly class LastPublicationsProvider implements ProviderInterface
{
    private const LAST_PUBLICATIONS_LIMIT = 4;

    public function __construct(
        private PublicationRepository $publicationRepository,
    ) {
    }

    /**
     * @return \App\Entity\Publication[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return $this->publicationRepository->findLastPublications(self::LAST_PUBLICATIONS_LIMIT);
    }
}
