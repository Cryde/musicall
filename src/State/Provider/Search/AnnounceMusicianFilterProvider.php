<?php declare(strict_types=1);

namespace App\State\Provider\Search;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Search\AnnounceMusicianFilter;
use App\Service\Finder\Musician\MusicianFilterGenerator;
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

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($search): ?AnnounceMusicianFilter {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->musicianFilterGenerator->find($search);
        });
    }
}
