<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Native;

use App\Security\Native\NativeCredentialsInBodyListener;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The API tests cover the two cookies a session really produces. This covers
 * the branches they cannot reach: a name this class has not been told about, and the guard that
 * keeps the whole thing off the web.
 */
class NativeCredentialsInBodyListenerTest extends TestCase
{
    public function test_it_moves_the_credential_cookies_into_the_body(): void
    {
        $response = new Response();
        $response->headers->setCookie(Cookie::create('refresh_token', 'a-refresh-token'));
        $response->headers->setCookie(
            Cookie::create('mercureAuthorization')->withValue('a-subscriber-token')->withPath('/.well-known/mercure')
        );
        $event = $this->eventFor($response, ['token' => 'a.jwt.here']);

        $this->listenerOn('/api/native/login_check')($event);

        // assertEquals, not assertSame: the fields land in the order ResponseHeaderBag hands its
        // cookies back, which is keyed by path, and the order of keys in a JSON object means nothing.
        $this->assertEquals([
            'token' => 'a.jwt.here',
            'mercure_authorization' => 'a-subscriber-token',
            'refresh_token' => 'a-refresh-token',
        ], $event->getData());
        $this->assertSame([], $response->headers->getCookies());
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    /**
     * A cookie this class does not know is still a credential, so it goes. The invariant is "no
     * Set-Cookie on the native surface", and it does not bend for an unfamiliar name. Silence would
     * mean a client quietly missing something it was meant to be given.
     */
    public function test_an_unknown_cookie_is_dropped_rather_than_returned(): void
    {
        $response = new Response();
        $response->headers->setCookie(Cookie::create('some_future_credential', 'secret'));
        $event = $this->eventFor($response);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $this->listenerOn('/api/native/login_check', $logger)($event);

        $this->assertSame([], $response->headers->getCookies());
        $this->assertArrayNotHasKey('some_future_credential', $event->getData());
        $this->assertSame(['token' => 'a.jwt.here'], $event->getData());
    }

    /**
     * The listener is registered globally, on the event every login and refresh dispatches, so this
     * guard is the only thing standing between it and the web's cookies.
     */
    public function test_it_leaves_a_web_response_completely_alone(): void
    {
        $response = new Response();
        $response->headers->setCookie(Cookie::create('refresh_token', 'a-refresh-token'));
        $event = $this->eventFor($response);

        $this->listenerOn('/api/login_check')($event);

        $this->assertCount(1, $response->headers->getCookies());
        $this->assertSame(['token' => 'a.jwt.here'], $event->getData());
        $this->assertStringNotContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    /**
     * The event carries no request, so the surface is read off the stack, which can be empty.
     */
    public function test_it_does_nothing_without_a_request(): void
    {
        $response = new Response();
        $response->headers->setCookie(Cookie::create('refresh_token', 'a-refresh-token'));
        $event = $this->eventFor($response);

        $listener = new NativeCredentialsInBodyListener(
            new RequestStack(),
            $this->createStub(LoggerInterface::class),
        );
        $listener($event);

        $this->assertCount(1, $response->headers->getCookies());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function eventFor(Response $response, array $data = ['token' => 'a.jwt.here']): AuthenticationSuccessEvent
    {
        return new AuthenticationSuccessEvent($data, $this->createStub(UserInterface::class), $response);
    }

    private function listenerOn(string $path, ?LoggerInterface $logger = null): NativeCredentialsInBodyListener
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create($path, 'POST'));

        return new NativeCredentialsInBodyListener(
            $requestStack,
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }
}
