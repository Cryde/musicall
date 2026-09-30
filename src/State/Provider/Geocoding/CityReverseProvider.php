<?php

declare(strict_types=1);

namespace App\State\Provider\Geocoding;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Geocoding\CityLookup;
use App\Service\Geocoding\PhotonGeocoder;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * « Use my location »: the town at the visitor's position, as a list of zero or one.
 *
 * @implements ProviderInterface<CityLookup>
 */
readonly class CityReverseProvider implements ProviderInterface
{
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

        $parameters = $operation->getParameters();
        $city = $this->geocoder->reverse(
            (float) $parameters?->get('latitude')?->getValue(),
            (float) $parameters?->get('longitude')?->getValue(),
        );

        return CityLookup::of($city !== null ? [$city] : []);
    }
}
