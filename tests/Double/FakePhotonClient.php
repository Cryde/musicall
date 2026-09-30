<?php

declare(strict_types=1);

namespace App\Tests\Double;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Stands in for Photon so the suite never calls komoot. Answers « lyon » and a point in Lyon from
 * recorded responses, anything else with no feature, and fails on demand.
 *
 * Registered as a decorator of photon.client under when@test in config/services.yaml.
 */
final class FakePhotonClient implements HttpClientInterface
{
    public const string BASE_URI = 'https://photon.komoot.io/';

    private readonly MockHttpClient $client;
    private bool $isDown = false;

    /** @var list<string> */
    public array $requestedUrls = [];

    public function __construct(HttpClientInterface $inner)
    {
        unset($inner);
        $this->client = new MockHttpClient($this->respond(...), self::BASE_URI);
    }

    public function goDown(): void
    {
        $this->isDown = true;
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        return $this->client->request($method, $url, $options);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        return $this;
    }

    private function respond(string $method, string $url): MockResponse
    {
        $this->requestedUrls[] = $url;
        if ($this->isDown) {
            return new MockResponse('', ['http_code' => 503]);
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $path = (string) parse_url($url, PHP_URL_PATH);
        $fixture = match (true) {
            $path === '/api/' && ($query['q'] ?? null) === 'lyon' => 'search-lyon.json',
            $path === '/reverse' && str_starts_with((string) ($query['lat'] ?? ''), '45.7') => 'reverse-lyon.json',
            default => null,
        };

        $body = $fixture !== null
            ? (string) file_get_contents(__DIR__ . '/../Fixtures/Photon/' . $fixture)
            : '{"type":"FeatureCollection","features":[]}';

        return new MockResponse($body, ['response_headers' => ['content-type' => 'application/json']]);
    }
}
