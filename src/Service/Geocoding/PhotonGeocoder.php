<?php

declare(strict_types=1);

namespace App\Service\Geocoding;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * City suggestions from Photon (komoot), cached. The same prefixes are typed all day long and places
 * do not move, so most keystrokes never leave Valkey; that also keeps us polite to a free shared
 * service now that every request comes from our one address.
 */
readonly class PhotonGeocoder
{
    // The places a location field offers: no streets, no shops, no regions.
    private const array CITY_TAGS = ['place:city', 'place:town', 'place:village', 'place:municipality'];
    private const int CACHE_TTL = 2592000; // 30 days
    // Short, so a string that matches nothing (a typo, or junk sent on purpose) does not sit for a month.
    private const int EMPTY_CACHE_TTL = 3600; // 1 hour
    // Always ask for the most any caller may want and slice, so one query is one cache entry whatever the limit.
    private const int FETCH_LIMIT = 10;
    // Three decimals is about 100 m: close enough to name the same town, coarse enough to share a cache entry.
    private const int REVERSE_PRECISION = 3;

    public function __construct(
        #[Target('photon.client')]
        private HttpClientInterface $photonClient,
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * An empty list when Photon is unreachable: a location field then offers nothing, the page still
     * works, and the failure is not cached.
     *
     * @return list<City>
     */
    public function searchCities(string $query, int $limit): array
    {
        $normalized = mb_strtolower(trim($query));
        $key = 'city_search_' . hash('sha256', $normalized);

        try {
            $cities = $this->cache->get($key, function (ItemInterface $item) use ($normalized): array {
                $cities = $this->fetchCities($normalized, self::FETCH_LIMIT);
                $item->expiresAfter($cities === [] ? self::EMPTY_CACHE_TTL : self::CACHE_TTL);

                return $cities;
            });
        } catch (ExceptionInterface $exception) {
            $this->logger->warning('Photon city search failed', ['exception' => $exception]);

            return [];
        }

        return array_slice($cities, 0, $limit);
    }

    /** The town at a point, or null when Photon has none there or cannot be reached. */
    public function reverse(float $latitude, float $longitude): ?City
    {
        $latitude = round($latitude, self::REVERSE_PRECISION);
        $longitude = round($longitude, self::REVERSE_PRECISION);
        $key = 'city_reverse_' . hash('sha256', $latitude . '|' . $longitude);

        try {
            return $this->cache->get($key, function (ItemInterface $item) use ($latitude, $longitude): ?City {
                $item->expiresAfter(self::CACHE_TTL);

                return $this->fetchReverse($latitude, $longitude);
            });
        } catch (ExceptionInterface $exception) {
            $this->logger->warning('Photon reverse geocoding failed', ['exception' => $exception]);

            return null;
        }
    }

    /** @return list<City> */
    private function fetchCities(string $query, int $limit): array
    {
        // Photon wants osm_tag repeated, which an array in `query` would send as osm_tag[0].
        $tags = implode('&', array_map(static fn (string $tag): string => 'osm_tag=' . urlencode($tag), self::CITY_TAGS));
        $url = 'api/?' . http_build_query(['q' => $query, 'limit' => $limit, 'lang' => 'fr']) . '&' . $tags;

        $cities = [];
        foreach ($this->photonClient->request('GET', $url)->toArray()['features'] ?? [] as $feature) {
            $properties = $feature['properties'] ?? [];
            $name = $properties['name'] ?? null;
            $point = $this->pointOf($feature);
            if (!is_string($name) || $point === null) {
                continue;
            }
            $cities[] = $this->city($name, $this->contextOf($properties), $point[0], $point[1]);
        }

        return $cities;
    }

    private function fetchReverse(float $latitude, float $longitude): ?City
    {
        // No osm_tag here: Photon returns the nearest feature of any kind, and its `city` names the town.
        $url = 'reverse?' . http_build_query(['lat' => $latitude, 'lon' => $longitude, 'lang' => 'fr']);
        $properties = $this->photonClient->request('GET', $url)->toArray()['features'][0]['properties'] ?? null;
        if (!is_array($properties)) {
            return null;
        }

        $name = $properties['city'] ?? $properties['name'] ?? $properties['county'] ?? $properties['state'] ?? null;
        if (!is_string($name) || $name === '') {
            return null;
        }

        $context = array_values(array_filter($this->contextOf($properties), static fn (string $part): bool => $part !== $name));

        return $this->city($name, $context, $latitude, $longitude);
    }

    /**
     * County, state and country, without blanks or repeats (a city-state names itself twice).
     *
     * @param array<string, mixed> $properties
     *
     * @return list<string>
     */
    private function contextOf(array $properties): array
    {
        $parts = [];
        foreach (['county', 'state', 'country'] as $key) {
            $part = $properties[$key] ?? null;
            if (is_string($part) && $part !== '' && !in_array($part, $parts, true)) {
                $parts[] = $part;
            }
        }

        return $parts;
    }

    /**
     * Photon's GeoJSON point is longitude first.
     *
     * @param array<string, mixed> $feature
     *
     * @return array{0: float, 1: float}|null latitude, longitude
     */
    private function pointOf(array $feature): ?array
    {
        $coordinates = $feature['geometry']['coordinates'] ?? null;
        if (!is_array($coordinates) || !is_numeric($coordinates[0] ?? null) || !is_numeric($coordinates[1] ?? null)) {
            return null;
        }

        return [(float) $coordinates[1], (float) $coordinates[0]];
    }

    /** @param list<string> $context */
    private function city(string $name, array $context, float $latitude, float $longitude): City
    {
        return new City(
            name: $name,
            context: implode(', ', $context),
            latitude: $latitude,
            longitude: $longitude,
            fullName: implode(', ', [$name, ...$context]),
        );
    }
}
