<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Native;

use App\Security\Native\BlindNativeSurfaceToCookiesListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class BlindNativeSurfaceToCookiesListenerTest extends TestCase
{
    /**
     * All three carriers, because clearing only the bag leaves Request::duplicate() able to rebuild
     * it from HTTP_COOKIE on a sub-request.
     */
    public function test_a_native_request_loses_every_cookie(): void
    {
        $request = $this->requestFor('/api/native/token/refresh');

        (new BlindNativeSurfaceToCookiesListener())($this->eventFor($request));

        $this->assertSame([], $request->cookies->all());
        $this->assertFalse($request->headers->has('cookie'));
        $this->assertNull($request->server->get('HTTP_COOKIE'));
    }

    /**
     * The web is the reason the split cookies exist and must keep working exactly as before.
     */
    public function test_a_request_outside_the_native_surface_keeps_its_cookies(): void
    {
        $request = $this->requestFor('/api/token/refresh');

        (new BlindNativeSurfaceToCookiesListener())($this->eventFor($request));

        $this->assertSame(['refresh_token' => 'a-refresh-token'], $request->cookies->all());
        $this->assertSame('refresh_token=a-refresh-token', $request->headers->get('cookie'));
    }

    /**
     * gesdinet's authenticator matches on the path with no method check, and SameSite=Lax sends
     * cookies on a cross-site top-level navigation, so a bare `window.location` reaches the refresh
     * endpoint. Blinding a GET is the half of that defence which does not depend on the router.
     */
    public function test_a_get_is_blinded_too(): void
    {
        $request = $this->requestFor('/api/native/token/refresh', 'GET');

        (new BlindNativeSurfaceToCookiesListener())($this->eventFor($request));

        $this->assertSame([], $request->cookies->all());
    }

    /**
     * A sub-request inherits the cookies of the one it was duplicated from, so it is blinded like
     * any other rather than waved through as Firewall waves it through.
     */
    public function test_a_sub_request_is_blinded(): void
    {
        $request = $this->requestFor('/api/native/login_check');

        (new BlindNativeSurfaceToCookiesListener())(
            $this->eventFor($request, HttpKernelInterface::SUB_REQUEST)
        );

        $this->assertSame([], $request->cookies->all());
    }

    public function test_it_runs_before_the_firewall(): void
    {
        // Firewall subscribes to kernel.request at 8. Anything above that works; the constant is
        // higher so nothing in the kernel sees a cookie either.
        $this->assertGreaterThan(8, BlindNativeSurfaceToCookiesListener::PRIORITY);
    }

    private function requestFor(string $path, string $method = 'POST'): Request
    {
        $request = Request::create($path, $method);
        $request->cookies->set('refresh_token', 'a-refresh-token');
        $request->headers->set('cookie', 'refresh_token=a-refresh-token');
        $request->server->set('HTTP_COOKIE', 'refresh_token=a-refresh-token');

        return $request;
    }

    private function eventFor(
        Request $request,
        int $type = HttpKernelInterface::MAIN_REQUEST,
    ): RequestEvent {
        return new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            $type,
        );
    }
}
