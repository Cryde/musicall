<?php

declare(strict_types=1);

namespace App\State\Provider\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Publication;
use App\Repository\PublicationRepository;

/**
 * @implements ProviderInterface<Publication>
 */
readonly class RelatedPublicationProvider implements ProviderInterface
{
    private const int RELATED_PUBLICATIONS_LIMIT = 2;

    public function __construct(
        private PublicationRepository $publicationRepository,
    ) {
    }

    /**
     * @return Publication[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $publication = $this->publicationRepository->findOneBy(['slug' => $uriVariables['slug']]);
        if (!$publication) {
            return [];
        }

        return $this->publicationRepository->findRelatedPublications($publication, self::RELATED_PUBLICATIONS_LIMIT);
    }
}
