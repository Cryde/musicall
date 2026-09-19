<?php

declare(strict_types=1);

namespace App\Security\Native;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hands a native client its session in the response body, and leaves it no cookies at all.
 *
 * The JWT is already there: the native firewalls use a success handler built with no cookie
 * providers, so Lexik never splits it. What is left is what the other listeners on this event
 * attach, the refresh token from gesdinet and the Mercure subscriber token from ours, both of which
 * are cookies because a browser is the only thing that can hold them for free.
 *
 * Done here rather than on kernel.response because this is the moment the values exist as objects:
 * setData() is the sanctioned way to add a body field, and the response has not been rendered. A
 * response listener would instead have to reassemble `jwt_hp . '.' . jwt_s` by hand, which
 * re-implements JWTSplitter in reverse and hard-codes today's `split:` configuration, and it would
 * leave the credentials sitting in Set-Cookie for the whole of the meantime.
 */
#[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_success', priority: self::PRIORITY)]
final readonly class NativeCredentialsInBodyListener
{
    /**
     * Below gesdinet's AttachRefreshTokenOnSuccessListener and our own
     * MercureSubscriberCookieListener, which are both tagged with no priority and so sit at 0. This
     * has to see the cookies they set, so it cannot share their priority.
     */
    public const int PRIORITY = -100;

    /**
     * The credential cookies a session is made of, and the body field that carries each one instead.
     */
    private const array FIELD_BY_COOKIE = [
        'refresh_token' => 'refresh_token',
        'mercureAuthorization' => 'mercure_authorization',
    ];

    public function __construct(
        private RequestStack $requestStack,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        // The event carries no request, so the surface is read off the stack, as
        // MercureSubscriberCookieListener already does for the same reason.
        if (!NativeSurface::covers($this->requestStack->getCurrentRequest())) {
            return;
        }

        $response = $event->getResponse();
        $data = $event->getData();

        foreach ($response->headers->getCookies() as $cookie) {
            $field = self::FIELD_BY_COOKIE[$cookie->getName()] ?? null;

            if ($field === null) {
                // Removed either way: "no Set-Cookie on the native surface" is the invariant and
                // does not bend for a name this class has not been told about. Logged because the
                // client then silently lacks a credential it was meant to get, and the alternative
                // is discovering that as a feature which does not work on mobile.
                $this->logger->warning('A native authentication response carried an unknown credential cookie, which was dropped rather than returned', [
                    'cookie' => $cookie->getName(),
                ]);
            } else {
                $data[$field] = $cookie->getValue();
            }

            // removeCookie() unsets it from the bag. clearCookie() would instead emit an expiring
            // one, which is still a Set-Cookie header.
            $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
        }

        $event->setData($data);

        // The web's credentials travel in Set-Cookie, which is never cached. These travel in a body,
        // which is.
        $response->headers->set('Cache-Control', 'no-store, private');
    }
}
