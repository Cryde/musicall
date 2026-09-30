<?php

declare(strict_types=1);

namespace App\State\Provider\Geocoding;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Geocoding\CityLookup;
use App\Service\Geocoding\PhotonGeocoder;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProviderInterface<CityLookup>
 */
readonly class CitySearchProvider implements ProviderInterface
{
    private const int DEFAULT_LIMIT = 5;

    public function __construct(
        private PhotonGeocoder $geocoder,
        private RequestStack $requestStack,
        #[Target('geocoding')]
        private RateLimiterFactoryInterface $geocodingLimiter,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CityLookup
    {
        $ip = $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown';
        $this->geocodingLimiter->create($ip)->consume()->ensureAccepted();

        // Both already validated by their declarations; an optional one left out reads as ParameterNotFound.
        $parameters = $operation->getParameters();
        $query = (string) $parameters?->get('q')?->getValue();
        $limit = $parameters?->get('limit')?->getValue();
        $limit = $limit instanceof ParameterNotFound ? self::DEFAULT_LIMIT : (int) $limit;

        return CityLookup::of($this->geocoder->searchCities($query, $limit));
    }
}
