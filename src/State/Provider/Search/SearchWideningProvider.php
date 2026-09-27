<?php

declare(strict_types=1);

namespace App\State\Provider\Search;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Search\Widening\SearchWidening;
use App\Entity\User;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\Service\Finder\Musician\Builder\SearchModelBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProviderInterface<SearchWidening>
 */
readonly class SearchWideningProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private SearchModelBuilder $searchModelBuilder,
        private MusicianAnnounceRepository $musicianAnnounceRepository,
        private RequestStack $requestStack,
        #[Target('musician_search_widen')]
        private RateLimiterFactoryInterface $widenLimiter,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): SearchWidening
    {
        $ip = $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown';
        $this->widenLimiter->create($ip)->consume()->ensureAccepted();

        $user = $this->security->getUser();
        $user = $user instanceof User ? $user : null;
        $search = $this->searchModelBuilder->buildFromParameters($operation->getParameters(), 1);

        $widening = new SearchWidening();

        // Only a search already bounded by a smaller radius can be widened by distance.
        if ($search->radius !== null && $search->radius < SearchWidening::WIDER_RADIUS && $search->latitude && $search->longitude) {
            $wider = clone $search;
            $wider->radius = SearchWidening::WIDER_RADIUS;
            $widening->widerRadius = $this->musicianAnnounceRepository->hasAnyByCriteria($wider, $user);
        }

        if ($search->styles !== []) {
            $anyStyle = clone $search;
            $anyStyle->styles = [];
            $widening->allStyles = $this->musicianAnnounceRepository->hasAnyByCriteria($anyStyle, $user);
        }

        return $widening;
    }
}
