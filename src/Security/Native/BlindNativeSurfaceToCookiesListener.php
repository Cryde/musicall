<?php

declare(strict_types=1);

namespace App\Security\Native;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Makes the native endpoints unable to see a cookie, which is the only thing that makes them safe.
 *
 * The native surface answers with the credentials in the body instead of in Set-Cookie. Deciding to
 * do that on anything the caller controls reopens the hole the split cookies close: an injected
 * script sets headers, so `X-Client: mobile` is worthless, and **a URL is caller-controlled in
 * exactly the same way**. Without this, script could POST /api/native/token/refresh with an empty
 * body, let the browser attach the httpOnly refresh_token by itself, and read a complete JWT out of
 * the response. It never has to read a cookie to do it. Worse, gesdinet's authenticator matches on
 * the path alone with no method check, and SameSite=Lax does send cookies on a cross-site top-level
 * navigation, so a plain `window.location` from any site would have done it too.
 *
 * Blinding the request removes the premise. The only way left to refresh natively is to already hold
 * the token, which is precisely what httpOnly denies to script. The rule underneath: it is safe to
 * accept two kinds of credential everywhere, and unsafe to issue them from the same place.
 *
 * This is an in-process stand-in for an origin boundary. The airtight version is a second host,
 * where the browser declines to send our cookies for us, at the price of DNS, TLS, a second Caddy
 * block, CORS and a second Mercure public URL.
 *
 * Everything goes, rather than the four names a session uses today: gesdinet's token_parameter_name
 * is per-firewall, so an allowlist would quietly stop covering the refresh token the day somebody
 * renames it, and no test would notice.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: self::PRIORITY)]
final readonly class BlindNativeSurfaceToCookiesListener
{
    /**
     * Anything above Firewall's 8 would work. This sits above ValidateRequestListener (256) and
     * RouterListener (32) so that nothing in the kernel ever observes a cookie on this surface,
     * which is easier to keep true than a number chosen to clear one listener.
     */
    public const int PRIORITY = 300;

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!NativeSurface::covers($request)) {
            return;
        }

        // Three carriers for one value. The bag is what the extractors read, the header is what
        // Sentry reports, and the server variable is what Request::duplicate() would rebuild the
        // other two from on a sub-request.
        $request->cookies->replace([]);
        $request->headers->remove('cookie');
        $request->server->remove('HTTP_COOKIE');
    }
}
