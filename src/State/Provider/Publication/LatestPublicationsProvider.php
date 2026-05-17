<?php

declare(strict_types=1);

namespace App\State\Provider\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\PublicationRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @implements ProviderInterface<\App\Entity\Publication>
 */
readonly class LatestPublicationsProvider implements ProviderInterface
{
    private const int DEFAULT_LIMIT = 3;
    private const int CACHE_TTL = 900; // 15 minutes

    public function __construct(
        private PublicationRepository $publicationRepository,
        private CacheInterface        $cache,
    ) {
    }

    /**
     * Query parameter bounds are enforced by the operation-level constraints on
     * `LatestPublication` (Assert\Positive, Assert\Range); the provider trusts the
     * sanitised values and only handles defaults / absence.
     *
     * @return \App\Entity\Publication[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $filters = $context['filters'] ?? [];

        $excludeId = isset($filters['excludeId']) && $filters['excludeId'] !== ''
            ? (int) $filters['excludeId']
            : null;

        $limit = isset($filters['count']) && $filters['count'] !== ''
            ? (int) $filters['count']
            : self::DEFAULT_LIMIT;

        $subCategoryType = isset($filters['subCategoryType']) && $filters['subCategoryType'] !== ''
            ? (int) $filters['subCategoryType']
            : null;

        $cacheKey = sprintf('latest_publications_%d_%d_%d', $excludeId ?? 0, $limit, $subCategoryType ?? 0);

        $ids = $this->cache->get(
            $cacheKey,
            function (ItemInterface $item) use ($excludeId, $limit, $subCategoryType): array {
                $item->expiresAfter(self::CACHE_TTL);

                return $this->publicationRepository->findLatestIdsExcluding($excludeId, $limit, $subCategoryType);
            }
        );

        return $this->publicationRepository->findOnlineByIdsOrdered($ids);
    }
}
