<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber\Observability;

use App\EventSubscriber\Observability\AuthFailureObservabilityListener;
use App\Security\Observability\AuthContextBuilder;
use App\Security\Observability\AuthFailureReason;
use App\Security\Observability\AuthLogger;
use App\Security\Observability\ConsumedRefreshTokenRegistry;
use Gesdinet\JWTRefreshTokenBundle\Event\RefreshAuthenticationFailureEvent;
use Gesdinet\JWTRefreshTokenBundle\Event\RefreshTokenNotFoundEvent;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ChainExtractor;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestBodyExtractor;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestCookieExtractor;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\MissingTokenException as GesdinetMissingTokenException;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\TokenNotFoundException;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\ExpiredTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\MissingTokenException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class AuthFailureObservabilityListenerTest extends TestCase
{
    /** @var list<array{string, array<string, mixed>}> */
    private array $records = [];

    private ArrayAdapter $pool;

    protected function setUp(): void
    {
        $this->records = [];
        $this->pool = new ArrayAdapter();
    }

    public function test_a_rejected_jwt_carries_its_reason(): void
    {
        $request = Request::create('/api/users/self');
        $request->cookies->set('jwt_s', 'a-signature');

        $this->listenerFor($request)->onJwtRejected(new JWTExpiredEvent(new ExpiredTokenException(), new Response()));

        $this->assertSame('auth.jwt.rejected', $this->records[0][0]);
        $this->assertSame('Expired JWT Token', $this->records[0][1]['reason']);
        $this->assertSame('failure', $this->records[0][1]['outcome']);
    }

    /**
     * A caller with no credential at all is an anonymous visitor reaching a protected endpoint. Those
     * are the bulk of the 401s, they answer none of the questions this exists to answer, and at
     * 5GB a month the volume is not free.
     */
    public function test_a_request_with_no_credential_is_not_logged(): void
    {
        $this->listenerFor(Request::create('/api/users/self'))
            ->onJwtRejected(new JWTNotFoundEvent(new MissingTokenException(), new Response()));

        $this->assertSame([], $this->records);
    }

    /**
     * The half present pair still counts: that is exactly the case under test, and it reaches the
     * server as "no token" rather than "expired token" because SplitCookieExtractor needs both.
     */
    public function test_a_half_present_cookie_pair_is_still_logged(): void
    {
        $request = Request::create('/api/users/self');
        $request->cookies->set('jwt_hp', 'a-header-payload');

        $this->listenerFor($request)->onJwtRejected(new JWTNotFoundEvent(new MissingTokenException(), new Response()));

        $this->assertCount(1, $this->records);
        $this->assertSame('JWT Token not found', $this->records[0][1]['reason']);
        $this->assertSame(
            ['jwt_hp' => true, 'jwt_s' => false, 'refresh_token' => false],
            $this->records[0][1]['cookies_present']
        );
    }

    /**
     * The classification the whole Valkey trail exists for. A token the server spent moments ago is
     * a race; the same failure with no trail behind it is something else entirely, and without this
     * field the two are the same log line.
     */
    public function test_a_refusal_of_a_just_spent_token_is_marked_as_previously_consumed(): void
    {
        $request = Request::create('/api/token/refresh', 'POST');
        $request->cookies->set('refresh_token', 'a-spent-token');

        $registry = new ConsumedRefreshTokenRegistry($this->pool, $this->createStub(LoggerInterface::class));
        $registry->remember(substr(hash('sha256', 'a-spent-token'), 0, 16), 'the-replacement', 'a-user-id', 'req-1');

        $this->listenerFor($request, $registry)
            ->onRefreshFailure(new RefreshAuthenticationFailureEvent(new TokenNotFoundException(), new Response()));

        $context = $this->records[0][1];
        $this->assertSame('auth.refresh.failure', $this->records[0][0]);
        $this->assertTrue($context['previously_consumed']);
        $this->assertIsInt($context['consumed_ago_ms']);
        $this->assertSame('req-1', $context['consumed_request_id']);
    }

    public function test_a_refusal_of_a_token_never_seen_is_marked_as_such(): void
    {
        $request = Request::create('/api/token/refresh', 'POST');
        $request->cookies->set('refresh_token', 'a-token-nobody-issued');

        $this->listenerFor($request)
            ->onRefreshFailure(new RefreshAuthenticationFailureEvent(new TokenNotFoundException(), new Response()));

        $this->assertFalse($this->records[0][1]['previously_consumed']);
    }

    public function test_a_missing_refresh_token_is_its_own_event(): void
    {
        $this->listenerFor(Request::create('/api/token/refresh', 'POST'))
            ->onRefreshTokenNotFound(new RefreshTokenNotFoundEvent(new MissingTokenException()));

        $this->assertSame('auth.refresh.not_found', $this->records[0][0]);
    }

    /**
     * The bundle reports "you sent nothing" and "what you sent was refused" through one event, and
     * they are not the same diagnosis. The first is a cookie that was never set or has been dropped.
     */
    public function test_sending_no_token_is_separated_from_sending_a_bad_one(): void
    {
        $this->listenerFor(Request::create('/api/token/refresh', 'POST'))
            ->onRefreshFailure(new RefreshAuthenticationFailureEvent(new GesdinetMissingTokenException(), new Response()));

        $this->assertSame('auth.refresh.not_found', $this->records[0][0]);
        $this->assertSame('Missing JWT Refresh Token', $this->records[0][1]['reason']);
    }

    /**
     * The kill switch has to reach every path, or turning it off leaves some of the volume behind.
     */
    public function test_nothing_is_logged_when_the_instrumentation_is_disabled(): void
    {
        $request = Request::create('/api/users/self');
        $request->cookies->set('jwt_s', 'a-signature');

        $this->listenerFor($request, enabled: false)
            ->onJwtRejected(new JWTExpiredEvent(new ExpiredTokenException(), new Response()));

        $this->assertSame([], $this->records);
    }

    private function listenerFor(
        Request $request,
        ?ConsumedRefreshTokenRegistry $registry = null,
        bool $enabled = true,
    ): AuthFailureObservabilityListener {
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

        $builder = new AuthContextBuilder($stack, $extractor, 'refresh_token');

        return new AuthFailureObservabilityListener(
            new AuthLogger($logger, $builder, $enabled),
            $builder,
            new AuthFailureReason(),
            $registry ?? new ConsumedRefreshTokenRegistry($this->pool, $this->createStub(LoggerInterface::class)),
            $stack,
            'refresh_token',
        );
    }
}
