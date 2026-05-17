<?php

declare(strict_types=1);

namespace App\State\Provider\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Publication\PopularTag;
use App\Repository\Publication\TagRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @implements ProviderInterface<PopularTag>
 */
readonly class PopularTagsProvider implements ProviderInterface
{
    private const int DEFAULT_LIMIT = 8;
    private const int CACHE_TTL = 21600; // 6 hours

    public function __construct(
        private TagRepository  $tagRepository,
        private CacheInterface $cache,
    ) {
    }

    /**
     * The `count` query parameter is range-validated at the operation level
     * (see `PopularTag`); the provider only handles the default when absent.
     *
     * @return PopularTag[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $filters = $context['filters'] ?? [];
        $limit = isset($filters['count']) && $filters['count'] !== ''
            ? (int) $filters['count']
            : self::DEFAULT_LIMIT;

        return $this->cache->get(
            sprintf('popular_tags_%d', $limit),
            function (ItemInterface $item) use ($limit): array {
                $item->expiresAfter(self::CACHE_TTL);

                return array_map(
                    fn (array $row): PopularTag => $this->buildFromRow($row),
                    $this->tagRepository->findPopularWithPublicationCount($limit),
                );
            }
        );
    }

    /**
     * @param array{tag: \App\Entity\Publication\Tag, count: int} $row
     */
    private function buildFromRow(array $row): PopularTag
    {
        $popular = new PopularTag();
        $popular->slug = $row['tag']->slug;
        $popular->label = $row['tag']->label;
        $popular->publicationCount = $row['count'];

        return $popular;
    }
}
