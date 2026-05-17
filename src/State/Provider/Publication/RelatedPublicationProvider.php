<?php

declare(strict_types=1);

namespace App\State\Provider\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Publication;
use App\Entity\Publication\Tag;
use App\Repository\PublicationRepository;

/**
 * @implements ProviderInterface<Publication>
 */
readonly class RelatedPublicationProvider implements ProviderInterface
{
    private const int RELATED_PUBLICATIONS_LIMIT = 3;

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
        if (!$publication instanceof Publication) {
            return [];
        }

        $publicationId = (int) $publication->id;
        $tagIds = array_map(static fn (Tag $tag): int => (int) $tag->id, $publication->tags->toArray());

        $byTag = $this->publicationRepository->findRelatedIdsByTags(
            $publicationId,
            $tagIds,
            self::RELATED_PUBLICATIONS_LIMIT,
        );

        if (count($byTag) >= self::RELATED_PUBLICATIONS_LIMIT) {
            return $this->publicationRepository->findOnlineByIdsOrdered($byTag);
        }

        $fallback = $this->publicationRepository->findRelatedIdsBySubCategory(
            $publicationId,
            (int) $publication->subCategory->id,
            self::RELATED_PUBLICATIONS_LIMIT - count($byTag),
            $byTag,
        );

        return $this->publicationRepository->findOnlineByIdsOrdered(array_merge($byTag, $fallback));
    }
}
