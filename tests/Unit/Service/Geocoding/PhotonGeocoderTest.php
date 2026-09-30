<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Geocoding;

use App\Service\Geocoding\City;
use App\Service\Geocoding\PhotonGeocoder;
use App\Tests\Double\FakePhotonClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * The cache is the point of proxying Photon: the same prefixes are typed all day, and every request
 * now leaves from our one address. These pin that a repeat never reaches Photon, that a failure is
 * never cached, and the mapping the front end used to do itself.
 */
final class PhotonGeocoderTest extends TestCase
{
    private FakePhotonClient $photon;
    private PhotonGeocoder $geocoder;

    protected function setUp(): void
    {
        $this->photon = new FakePhotonClient(new MockHttpClient());
        $this->geocoder = new PhotonGeocoder($this->photon, new ArrayAdapter(), new NullLogger());
    }

    public function test_it_maps_cities_and_skips_a_feature_without_coordinates(): void
    {
        $cities = $this->geocoder->searchCities('lyon', 5);

        $this->assertEquals([
            new City('Lyon', 'Rhône, Auvergne-Rhône-Alpes, France', 45.7578137, 4.8320114, 'Lyon, Rhône, Auvergne-Rhône-Alpes, France'),
            new City('Lyons-la-Forêt', 'Eure, Normandie, France', 49.3997, 1.4714, 'Lyons-la-Forêt, Eure, Normandie, France'),
            new City('Lyon', 'Virginia, États-Unis', 37.2099, -79.6389, 'Lyon, Virginia, États-Unis'),
        ], $cities);
    }

    public function test_it_asks_photon_for_places_only(): void
    {
        $this->geocoder->searchCities('lyon', 5);

        $this->assertSame(
            ['https://photon.komoot.io/api/?q=lyon&limit=10&lang=fr&osm_tag=place%3Acity&osm_tag=place%3Atown&osm_tag=place%3Avillage&osm_tag=place%3Amunicipality'],
            $this->photon->requestedUrls,
        );
    }

    public function test_a_repeated_search_is_answered_from_the_cache_whatever_its_case(): void
    {
        $first = $this->geocoder->searchCities('lyon', 5);
        $second = $this->geocoder->searchCities('  LYON ', 5);

        $this->assertEquals($first, $second);
        $this->assertCount(1, $this->photon->requestedUrls);
    }

    public function test_a_smaller_limit_is_sliced_from_the_same_cached_lookup(): void
    {
        $this->geocoder->searchCities('lyon', 5);
        $first = $this->geocoder->searchCities('lyon', 1);

        $this->assertEquals(
            [new City('Lyon', 'Rhône, Auvergne-Rhône-Alpes, France', 45.7578137, 4.8320114, 'Lyon, Rhône, Auvergne-Rhône-Alpes, France')],
            $first,
        );
        $this->assertCount(1, $this->photon->requestedUrls);
    }

    public function test_an_unreachable_photon_gives_no_suggestion_and_is_not_cached(): void
    {
        $this->photon->goDown();

        $this->assertSame([], $this->geocoder->searchCities('lyon', 5));
        $this->assertSame([], $this->geocoder->searchCities('lyon', 5));
        $this->assertCount(2, $this->photon->requestedUrls);
    }

    public function test_reverse_names_the_town_and_drops_it_from_the_context(): void
    {
        $city = $this->geocoder->reverse(45.76403, 4.83572);

        $this->assertEquals(new City('Lyon', 'Auvergne-Rhône-Alpes, France', 45.764, 4.836, 'Lyon, Auvergne-Rhône-Alpes, France'), $city);
    }

    public function test_two_points_a_few_metres_apart_share_one_reverse_lookup(): void
    {
        $this->geocoder->reverse(45.76403, 4.83572);
        $this->geocoder->reverse(45.76411, 4.83568);

        $this->assertCount(1, $this->photon->requestedUrls);
    }

    public function test_reverse_finds_nothing_in_the_middle_of_nowhere(): void
    {
        $this->assertNull($this->geocoder->reverse(0.0, -30.0));
    }

    public function test_an_unreachable_photon_gives_no_town(): void
    {
        $this->photon->goDown();

        $this->assertNull($this->geocoder->reverse(45.764, 4.836));
    }
}
