<?php declare(strict_types=1);

namespace App\State\Provider\Search;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Search\AnnounceMusicianFilter;
use App\Enum\Search\AiSearchOutcome;
use App\Exception\Musician\InvalidResultException;
use App\Exception\Musician\NoResultException;
use App\Service\Finder\Musician\MusicianFilterGenerator;
use App\Service\Search\MusicianSearchRecorder;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @implements ProviderInterface<AnnounceMusicianFilter>
 */
readonly class AnnounceMusicianFilterProvider implements ProviderInterface
{
    private const int CACHE_TTL = 36000; // 10 hours

    public function __construct(
        private MusicianFilterGenerator $musicianFilterGenerator,
        private CacheInterface $cache,
        #[Target('musician_search')]
        private RateLimiterFactoryInterface $musicianSearchLimiter,
        private RequestStack $requestStack,
        private MusicianSearchRecorder $musicianSearchRecorder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?AnnounceMusicianFilter
    {
        if (!($params = $operation->getParameters()) instanceof \ApiPlatform\Metadata\Parameters) {
            return null;
        }
        $search = $params->get('search')?->getValue();

        $ip = $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown';
        $this->musicianSearchLimiter->create($ip)->consume()->ensureAccepted();

        $cacheKey = 'musician_filter_' . hash('sha256', mb_strtolower(trim($search)));

        // Recorded outside the cache, so a question answered from it still counts as one asked (#1075).
        try {
            $filters = $this->cache->get($cacheKey, function (ItemInterface $item) use ($search): ?AnnounceMusicianFilter {
                $item->expiresAfter(self::CACHE_TTL);

                return $this->musicianFilterGenerator->find($search);
            });
        } catch (NoResultException|InvalidResultException $exception) {
            $this->musicianSearchRecorder->recordAiSearch($search, AiSearchOutcome::Failed);

            throw $exception;
        }

        $this->musicianSearchRecorder->recordAiSearch(
            $search,
            $filters instanceof AnnounceMusicianFilter ? AiSearchOutcome::Filters : AiSearchOutcome::Nothing,
            $filters,
        );

        return $filters;
    }
}
