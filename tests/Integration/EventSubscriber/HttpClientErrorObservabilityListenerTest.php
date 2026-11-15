<?php

declare(strict_types=1);

namespace App\Tests\Integration\EventSubscriber;

use ApiPlatform\Symfony\Security\Exception\AccessDeniedException;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Through the whole kernel: the listener only sees the exception if it runs before the listener that
 * renders the response, and the error page sub-request must not be counted a second time.
 */
class HttpClientErrorObservabilityListenerTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private TestHandler $records;

    protected function setUp(): void
    {
        parent::setUp();
        $logger = self::getContainer()->get('monolog.logger.http');
        self::assertInstanceOf(Logger::class, $logger);
        $this->records = new TestHandler();
        $logger->pushHandler($this->records);
    }

    public function test_an_anonymous_401_from_the_firewall_is_logged_once_with_its_exception(): void
    {
        $this->client->request('GET', '/api/users/self', server: ['HTTP_X_CLIENT_PLATFORM' => 'ios', 'HTTP_X_CLIENT_VERSION' => '1.13.0']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
        $this->assertSame([[
            'event' => 'http.client_error',
            'status' => 401,
            'route' => 'api_users_get_self',
            'method' => 'GET',
            'message' => 'Access Denied. The user is not appropriately authenticated.',
            'exception_class' => AccessDeniedException::class,
            'client_platform' => 'ios',
            'client_version' => '1.13.0',
            'referer_host' => null,
        ]], $this->loggedContexts());
    }

    public function test_an_unknown_route_is_logged_with_its_path(): void
    {
        $this->client->request('GET', '/api/session/properties');

        // No route, so no API Platform operation: the body is Symfony's HTML error page, not JSON.
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame([[
            'event' => 'http.client_error',
            'status' => 404,
            'route' => '',
            'method' => 'GET',
            'message' => null,
            'exception_class' => NotFoundHttpException::class,
            'client_platform' => null,
            'client_version' => null,
            'referer_host' => null,
            'path' => '/api/session/properties',
        ]], $this->loggedContexts());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loggedContexts(): array
    {
        return array_map(static fn ($record): array => $record->context, $this->records->getRecords());
    }
}
