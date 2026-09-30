<?php

declare(strict_types=1);

namespace App\Tests\Api\Geocoding;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\FakePhotonClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * The city pickers now ask us rather than komoot. Public on purpose, like the pickers on the home
 * page, which is why it is rate limited per address: an open, unlimited proxy to Photon would get
 * our server throttled for everyone.
 */
final class CityLookupTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_visitor_gets_city_suggestions(): void
    {
        $this->client->request('GET', '/api/geocoding/cities?q=lyon&limit=5');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/CityLookup',
            '@id' => '/api/geocoding/cities',
            '@type' => 'CityLookup',
            'cities' => [
                [
                    '@type' => 'City',
                    'name' => 'Lyon',
                    'context' => 'Rhône, Auvergne-Rhône-Alpes, France',
                    'latitude' => 45.7578137,
                    'longitude' => 4.8320114,
                    'full_name' => 'Lyon, Rhône, Auvergne-Rhône-Alpes, France',
                ],
                [
                    '@type' => 'City',
                    'name' => 'Lyons-la-Forêt',
                    'context' => 'Eure, Normandie, France',
                    'latitude' => 49.3997,
                    'longitude' => 1.4714,
                    'full_name' => 'Lyons-la-Forêt, Eure, Normandie, France',
                ],
                [
                    '@type' => 'City',
                    'name' => 'Lyon',
                    'context' => 'Virginia, États-Unis',
                    'latitude' => 37.2099,
                    'longitude' => -79.6389,
                    'full_name' => 'Lyon, Virginia, États-Unis',
                ],
            ],
        ]);
    }

    public function test_nothing_found_is_an_empty_list(): void
    {
        $this->client->request('GET', '/api/geocoding/cities?q=xyzzy');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/CityLookup',
            '@id' => '/api/geocoding/cities',
            '@type' => 'CityLookup',
            'cities' => [],
        ]);
    }

    public function test_photon_being_down_is_an_empty_list_not_an_error(): void
    {
        self::getContainer()->get(FakePhotonClient::class)->goDown();

        $this->client->request('GET', '/api/geocoding/cities?q=lyon');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/CityLookup',
            '@id' => '/api/geocoding/cities',
            '@type' => 'CityLookup',
            'cities' => [],
        ]);
    }

    public function test_a_single_letter_is_refused(): void
    {
        $this->client->request('GET', '/api/geocoding/cities?q=l');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'q',
                    'message' => 'Saisissez au moins 2 caractères',
                    'code' => '9ff3fdc4-b214-49db-8718-39c315e33d45',
                ],
            ],
            'detail' => 'q: Saisissez au moins 2 caractères',
            'description' => 'q: Saisissez au moins 2 caractères',
            'type' => '/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            'title' => 'An error occurred',
        ]);
    }

    public function test_a_search_without_query_is_refused(): void
    {
        $this->client->request('GET', '/api/geocoding/cities');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'q',
                    'message' => 'Saisissez une ville',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'q: Saisissez une ville',
            'description' => 'q: Saisissez une ville',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
        ]);
    }

    public function test_a_query_of_spaces_only_is_refused(): void
    {
        $this->client->request('GET', '/api/geocoding/cities?q=%20%20%20');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'q',
                    'message' => 'Saisissez une ville',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'q: Saisissez une ville',
            'description' => 'q: Saisissez une ville',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
        ]);
    }

    public function test_a_null_byte_in_the_query_is_refused(): void
    {
        $this->client->request('GET', '/api/geocoding/cities?q=ly%00on');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'q',
                    'message' => 'La recherche contient des caractères invalides',
                    'code' => 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
                ],
            ],
            'detail' => 'q: La recherche contient des caractères invalides',
            'description' => 'q: La recherche contient des caractères invalides',
            'type' => '/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            'title' => 'An error occurred',
        ]);
    }

    public function test_a_query_sent_as_an_array_is_refused(): void
    {
        $this->client->request('GET', '/api/geocoding/cities?q[]=lyon');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/ba785a8c-82cb-4283-967c-3cf342181b40',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'q',
                    'message' => 'La recherche doit être un texte',
                    'code' => 'ba785a8c-82cb-4283-967c-3cf342181b40',
                ],
            ],
            'detail' => 'q: La recherche doit être un texte',
            'description' => 'q: La recherche doit être un texte',
            'type' => '/validation_errors/ba785a8c-82cb-4283-967c-3cf342181b40',
            'title' => 'An error occurred',
        ]);
    }

    public function test_a_limit_out_of_range_is_refused(): void
    {
        $this->client->request('GET', '/api/geocoding/cities?q=lyon&limit=50');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'limit',
                    'message' => 'Le nombre de suggestions doit être compris entre 1 et 10',
                    'code' => '04b91c99-a946-4221-afc5-e65ebac401eb',
                ],
            ],
            'detail' => 'limit: Le nombre de suggestions doit être compris entre 1 et 10',
            'description' => 'limit: Le nombre de suggestions doit être compris entre 1 et 10',
            'type' => '/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            'title' => 'An error occurred',
        ]);
    }

    public function test_a_visitor_gets_the_town_at_their_position(): void
    {
        $this->client->request('GET', '/api/geocoding/reverse?latitude=45.76403&longitude=4.83572');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/CityLookup',
            '@id' => '/api/geocoding/reverse',
            '@type' => 'CityLookup',
            'cities' => [
                [
                    '@type' => 'City',
                    'name' => 'Lyon',
                    'context' => 'Auvergne-Rhône-Alpes, France',
                    'latitude' => 45.764,
                    'longitude' => 4.836,
                    'full_name' => 'Lyon, Auvergne-Rhône-Alpes, France',
                ],
            ],
        ]);
    }

    public function test_a_malformed_latitude_is_refused(): void
    {
        $this->client->request('GET', '/api/geocoding/reverse?latitude=%00&longitude=4.8');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'latitude',
                    'message' => 'La latitude est invalide',
                    'code' => 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
                ],
            ],
            'detail' => 'latitude: La latitude est invalide',
            'description' => 'latitude: La latitude est invalide',
            'type' => '/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            'title' => 'An error occurred',
        ]);
    }

    public function test_a_reverse_lookup_without_longitude_is_refused(): void
    {
        $this->client->request('GET', '/api/geocoding/reverse?latitude=45.76');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'longitude',
                    'message' => 'Précisez une longitude',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'longitude: Précisez une longitude',
            'description' => 'longitude: Précisez une longitude',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
        ]);
    }

    public function test_the_lookups_are_rate_limited(): void
    {
        /** @var RateLimiterFactoryInterface $limiter */
        $limiter = self::getContainer()->get('limiter.geocoding');
        $limiter->create('127.0.0.1')->consume(120);

        $this->client->request('GET', '/api/geocoding/cities?q=lyon');

        $this->assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/429',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Rate Limit Exceeded',
            'status' => 429,
            'type' => '/errors/429',
            'description' => 'Rate Limit Exceeded',
        ]);
    }
}
