<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber\Observability;

use App\EventSubscriber\Observability\HttpClientErrorObservabilityListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\RateLimiter\RateLimit;

class HttpClientErrorObservabilityListenerTest extends TestCase
{
    /** @var list<array{string, array<string, mixed>}> */
    private array $records = [];

    private HttpClientErrorObservabilityListener $listener;

    protected function setUp(): void
    {
        $this->records = [];
        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('info')->willReturnCallback(
            function (string $message, array $context): void {
                $this->records[] = [$message, $context];
            }
        );
        $this->listener = new HttpClientErrorObservabilityListener($logger);
    }

    /**
     * The case the path is logged for: our own client calling a route that does not exist. The
     * router's message quotes the full referer, so it is not logged.
     */
    public function test_an_unknown_route_logs_an_empty_route_and_the_path(): void
    {
        $request = Request::create('/api/session/properties?token=secret');
        $request->headers->set('Referer', 'https://www.musicall.com/reset-password/a-token');

        $this->handle($request, new NotFoundHttpException('No route found for "GET http://musicall.test/api/session/properties" (from "https://www.musicall.com/reset-password/a-token")'), 404);

        $this->assertSame([[
            'http.client_error',
            [
                'event' => 'http.client_error',
                'status' => 404,
                'route' => '',
                'method' => 'GET',
                'message' => null,
                'exception_class' => NotFoundHttpException::class,
                'client_platform' => null,
                'client_version' => null,
                'referer_host' => 'www.musicall.com',
                'path' => '/api/session/properties',
            ],
        ]], $this->records);
    }

    /**
     * A token route refusing the method matches no route either, and its token must not be logged.
     */
    public function test_a_token_in_an_unmatched_path_is_masked(): void
    {
        $this->handle(Request::create('/api/users/reset-password/0123456789abcdef0123456789abcdef'), null, 405);

        $this->assertSame('/api/users/reset-password/{redacted}', $this->records[0][1]['path']);
    }

    public function test_a_long_route_segment_of_an_unmatched_path_is_kept(): void
    {
        $this->handle(Request::create('/api/band_space_tech_rider_stage_plot_icons/' . str_repeat('aB_-', 11)), null, 404);

        $this->assertSame('/api/band_space_tech_rider_stage_plot_icons/{redacted}', $this->records[0][1]['path']);
    }

    public function test_a_provider_404_logs_the_route_and_the_message_key_but_no_path(): void
    {
        $request = Request::create('/api/users/a-username/teacher-profile');
        $request->attributes->set('_route', '_api_/users/{username}/teacher-profile_get');

        $this->handle($request, new NotFoundHttpException('user.teacher_profile.not_found'), 404);

        $this->assertSame([[
            'http.client_error',
            [
                'event' => 'http.client_error',
                'status' => 404,
                'route' => '_api_/users/{username}/teacher-profile_get',
                'method' => 'GET',
                'message' => 'user.teacher_profile.not_found',
                'exception_class' => NotFoundHttpException::class,
                'client_platform' => null,
                'client_version' => null,
                'referer_host' => null,
            ],
        ]], $this->records);
    }

    /**
     * The exception alone does not know its status: exception_to_status in api_platform.yaml does.
     */
    public function test_a_mapped_exception_logs_the_status_of_the_response(): void
    {
        $request = Request::create('/api/login', 'POST');
        $request->attributes->set('_route', 'api_login_check');

        $this->handle($request, new RateLimitExceededException(new RateLimit(0, new \DateTimeImmutable(), false, 5)), 429);

        $this->assertSame(429, $this->records[0][1]['status']);
        $this->assertSame('Rate Limit Exceeded', $this->records[0][1]['message']);
        $this->assertSame(RateLimitExceededException::class, $this->records[0][1]['exception_class']);
    }

    public function test_the_client_headers_land_in_the_attributes(): void
    {
        $request = Request::create('/api/notifications/count');
        $request->attributes->set('_route', '_api_/notifications/count_get');
        $request->headers->set('X-Client-Platform', 'android');
        $request->headers->set('X-Client-Version', '1.13.0+42');

        $this->handle($request, null, 401);

        $this->assertSame([[
            'http.client_error',
            [
                'event' => 'http.client_error',
                'status' => 401,
                'route' => '_api_/notifications/count_get',
                'method' => 'GET',
                'message' => null,
                'exception_class' => null,
                'client_platform' => 'android',
                'client_version' => '1.13.0+42',
                'referer_host' => null,
            ],
        ]], $this->records);
    }

    public function test_an_app_build_older_than_the_headers_is_named_by_its_bearer_token(): void
    {
        $request = Request::create('/api/removed-route');
        $request->headers->set('Authorization', 'Bearer a-token');

        $this->handle($request, new NotFoundHttpException(), 404);

        $this->assertSame('unknown_app', $this->records[0][1]['client_platform']);
        $this->assertNull($this->records[0][1]['client_version']);
    }

    public function test_a_success_logs_nothing(): void
    {
        $this->handle(Request::create('/api/users/self'), null, 200);

        $this->assertSame([], $this->records);
    }

    /**
     * A 5xx is an error, and Sentry errors already holds it.
     */
    public function test_a_server_error_logs_nothing(): void
    {
        $this->handle(Request::create('/api/users/self'), new \RuntimeException('boom'), 500);

        $this->assertSame([], $this->records);
    }

    /**
     * The error page is rendered by a sub-request: counting it as well would log every 4xx twice.
     */
    public function test_a_sub_request_response_logs_nothing(): void
    {
        $this->handle(Request::create('/api/users/self'), new NotFoundHttpException(), 404, HttpKernelInterface::SUB_REQUEST);

        $this->assertSame([], $this->records);
    }

    private function handle(
        Request $request,
        ?\Throwable $exception,
        int $status,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
    ): void {
        $kernel = $this->createStub(HttpKernelInterface::class);

        if ($exception instanceof \Throwable) {
            $this->listener->keepException(new ExceptionEvent($kernel, $request, $requestType, $exception));
        }

        $this->listener->logClientError(new ResponseEvent($kernel, $request, $requestType, new Response('', $status)));
    }
}
