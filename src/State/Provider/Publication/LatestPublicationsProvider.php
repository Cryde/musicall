<?php

declare(strict_types=1);

namespace App\State\Provider\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Publication\PublicationListItem;
use App\Repository\PublicationRepository;
use App\Service\Builder\Publication\PublicationListItemBuilder;
use App\Service\Metric\PublicationUserVoteResolver;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @implements ProviderInterface<PublicationListItem>
 */
readonly class LatestPublicationsProvider implements ProviderInterface
{
    private const int DEFAULT_LIMIT = 3;
    private const int CACHE_TTL = 900; // 15 minutes

    public function __construct(
        private PublicationRepository       $publicationRepository,
        private PublicationListItemBuilder  $publicationListItemBuilder,
        private PublicationUserVoteResolver $userVoteResolver,
        private CacheInterface              $cache,
        private LocaleSwitcher              $localeSwitcher,
    ) {
    }

    /**
     * Query parameter bounds are enforced by the operation-level constraints on
     * the GetCollection operation (Assert\Positive, Assert\Range); the provider
     * trusts the sanitised values and only handles defaults / absence.
     *
     * @return PublicationListItem[]
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

        // The locale is one of the enabled ones, never free input, so it goes in the key as it is.
        $locale = $this->localeSwitcher->getLocale();
        $cacheKey = sprintf('latest_publications_%s_%d_%d_%d', $locale, $excludeId ?? 0, $limit, $subCategoryType ?? 0);

        // Cache the list of ids only; vote resolution stays per-request so it
        // can't leak across users.
        $ids = $this->cache->get(
            $cacheKey,
            function (ItemInterface $item) use ($locale, $excludeId, $limit, $subCategoryType): array {
                $item->expiresAfter(self::CACHE_TTL);

                return $this->publicationRepository->findLatestIdsExcluding($locale, $excludeId, $limit, $subCategoryType);
            }
        );

        $publications = $this->publicationRepository->findOnlineByIdsOrdered($ids);

        return $this->publicationListItemBuilder->buildList(
            $publications,
            $this->userVoteResolver->resolveForPublications($publications),
        );
    }
}
