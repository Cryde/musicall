<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber\Observability;

use App\Entity\User;
use App\EventSubscriber\Observability\AuthSuccessObservabilityListener;
use App\Security\Observability\AuthContextBuilder;
use App\Security\Observability\AuthLogger;
use App\Security\Observability\ConsumedRefreshTokenRegistry;
use Gesdinet\JWTRefreshTokenBundle\Event\RefreshEvent;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ChainExtractor;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestBodyExtractor;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestCookieExtractor;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AuthSuccessObservabilityListenerTest extends TestCase
{
    /** @var list<array{string, array<string, mixed>}> */
    private array $records = [];

    private ArrayAdapter $pool;

    protected function setUp(): void
    {
        $this->records = [];
        $this->pool = new ArrayAdapter();
    }

    /**
     * AuthenticationSuccessEvent fires for a login and for a refresh alike and carries neither the
     * token nor the request, so the two are told apart by the mark gesdinet's own event leaves on
     * the request. Without a mark it is a login.
     */
    public function test_a_login_is_logged_as_a_login(): void
    {
        $request = Request::create('/api/login_check', 'POST');
        [$listener] = $this->listenerFor($request);

        $listener->onAuthenticationSuccess($this->successEvent());

        $this->assertSame('auth.login.success', $this->records[0][0]);
        $this->assertSame('success', $this->records[0][1]['outcome']);
    }

    public function test_a_refresh_is_logged_as_a_refresh_with_its_replacement(): void
    {
        $request = Request::create('/api/token/refresh', 'POST');
        $request->cookies->set('refresh_token', 'the-old-token');
        [$listener] = $this->listenerFor($request);

        $listener->onRefreshTokenConsumed($this->refreshEvent($request));

        $response = new Response();
        $response->headers->setCookie(Cookie::create('refresh_token', 'the-new-token'));
        $listener->onAuthenticationSuccess($this->successEvent($response));

        $context = $this->records[0][1];
        $this->assertSame('auth.refresh.success', $this->records[0][0]);
        $this->assertSame(substr(hash('sha256', 'the-old-token'), 0, 16), $context['token_hash']);
        $this->assertSame(substr(hash('sha256', 'the-new-token'), 0, 16), $context['replacement_hash']);
    }

    /**
     * The write that makes the race classification possible at all. Without it a later refusal of
     * this same token is indistinguishable from a token that never existed.
     */
    public function test_the_spent_token_is_recorded_so_a_later_refusal_can_be_classified(): void
    {
        $request = Request::create('/api/token/refresh', 'POST');
        $request->cookies->set('refresh_token', 'the-old-token');
        [$listener, $registry] = $this->listenerFor($request);

        $response = new Response();
        $response->headers->setCookie(Cookie::create('refresh_token', 'the-new-token'));

        $listener->onRefreshTokenConsumed($this->refreshEvent($request));
        $listener->onAuthenticationSuccess($this->successEvent($response));

        $trail = $registry->lookup(substr(hash('sha256', 'the-old-token'), 0, 16));

        $this->assertTrue($trail['previously_consumed']);
        $this->assertSame(substr(hash('sha256', 'the-new-token'), 0, 16), $trail['consumed_replacement_hash']);
        $this->assertSame('0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b', $this->records[0][1]['user_ref']);
        $this->assertLessThan(1000, $trail['consumed_ago_ms']);
    }

    /**
     * A native client is handed its replacement in the body rather than on a cookie, because
     * NativeCredentialsInBodyListener has already moved it by the time this listener runs.
     */
    public function test_a_native_refresh_finds_its_replacement_in_the_body(): void
    {
        $request = Request::create('/api/native/token/refresh', 'POST', [], [], [], [], json_encode(['refresh_token' => 'the-old-token']));
        [$listener] = $this->listenerFor($request);

        $listener->onRefreshTokenConsumed($this->refreshEvent($request));
        $listener->onAuthenticationSuccess($this->successEvent(data: ['refresh_token' => 'the-new-token']));

        $this->assertSame(
            substr(hash('sha256', 'the-new-token'), 0, 16),
            $this->records[0][1]['replacement_hash']
        );
    }

    public function test_nothing_is_logged_when_the_instrumentation_is_disabled(): void
    {
        [$listener] = $this->listenerFor(Request::create('/api/login_check', 'POST'), enabled: false);

        $listener->onAuthenticationSuccess($this->successEvent());

        $this->assertSame([], $this->records);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function successEvent(?Response $response = null, array $data = []): AuthenticationSuccessEvent
    {
        $user = new User();
        $user->id = '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b';
        $user->username = 'a-username';

        return new AuthenticationSuccessEvent($data, $user, $response ?? new Response());
    }

    private function refreshEvent(Request $request): RefreshEvent
    {
        return new RefreshEvent(
            $this->createStub(RefreshTokenInterface::class),
            $this->createStub(TokenInterface::class),
            'api_token_refresh',
            $request,
        );
    }

    /**
     * @return array{AuthSuccessObservabilityListener, ConsumedRefreshTokenRegistry}
     */
    private function listenerFor(Request $request, bool $enabled = true): array
    {
        $extractor = new ChainExtractor();
        $extractor->addExtractor(new RequestCookieExtractor());
        $extractor->addExtractor(new RequestBodyExtractor());

        $stack = new RequestStack();
        $stack->push($request);

        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('info')->willReturnCallback(
            function (string $message, array $context): void {
                $this->records[] = [$message, $context];
            }
        );

        $builder = new AuthContextBuilder($stack, $extractor, 'refresh_token', 'v1');
        $registry = new ConsumedRefreshTokenRegistry($this->pool, $this->createStub(LoggerInterface::class));

        return [
            new AuthSuccessObservabilityListener(
                new AuthLogger($logger, $builder, $enabled),
                $builder,
                $registry,
                $stack,
                'refresh_token',
            ),
            $registry,
        ];
    }
}
