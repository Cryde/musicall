<?php

declare(strict_types=1);

namespace App\State\Provider\Search;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Search\Frequent\FrequentSearch;
use App\ApiResource\Search\Frequent\FrequentSearches;
use App\Repository\Search\MusicianSearchLogRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @implements ProviderInterface<FrequentSearches>
 */
readonly class FrequentSearchesProvider implements ProviderInterface
{
    public const int WINDOW_DAYS = 30;
    /** Different people, not searches, so one person repeating a search cannot put it on the homepage. */
    public const int MIN_VISITORS = 3;
    public const int LIMIT = 4;
    private const int CACHE_TTL = 3600; // an hour: the row follows a month of searches, not the last minute
    private const string CACHE_KEY = 'frequent_musician_searches';

    public function __construct(
        private MusicianSearchLogRepository $musicianSearchLogRepository,
        private CacheInterface $cache,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): FrequentSearches
    {
        $frequentSearches = new FrequentSearches();
        $frequentSearches->searches = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL);
            $rows = $this->musicianSearchLogRepository->findFrequentSearches(
                new \DateTimeImmutable(sprintf('-%d days', self::WINDOW_DAYS)),
                self::MIN_VISITORS,
                self::LIMIT,
            );

            return array_map(static function (array $row): FrequentSearch {
                $search = new FrequentSearch();
                $search->type = $row['type'];
                $search->instrumentId = $row['instrumentId'];
                $search->instrumentName = $row['instrumentName'];
                $search->locationName = $row['locationName'];
                $search->latitude = $row['latitude'];
                $search->longitude = $row['longitude'];

                return $search;
            }, $rows);
        });

        return $frequentSearches;
    }
}
