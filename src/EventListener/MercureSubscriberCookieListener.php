<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Mercure\MercureSubscriberCookie;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Renews the Mercure subscriber cookie every time a JWT is issued.
 *
 * One listener covers both ways that happens through the firewall: gesdinet's refresh handler
 * delegates to Lexik's, which is the only thing that dispatches this event, so `POST /api/login_check`
 * and `POST /api/token/refresh` both land here and the subscriber token tracks the JWT it accompanies.
 * The OAuth callback mints its cookies by hand and never reaches the handler, so
 * AbstractOAuthController calls the same service itself.
 *
 * Kept separate from AuthenticationSuccessListener, which listens to the same event to stamp
 * lastLoginDatetime: one reason to change each.
 */
#[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_success')]
final readonly class MercureSubscriberCookieListener
{
    public function __construct(
        private MercureSubscriberCookie $subscriberCookie,
        private RequestStack $requestStack,
    ) {
    }

    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        // Authorization::createCookie() demands a Request in order to scope the cookie's domain
        // against it. Under this configuration it never reads it, because a host-less public_url has
        // no domain to derive, which MercureHubConfigurationTest pins. The main request rather than
        // the current one anyway, so that stays true if a host is ever put back.
        $request = $this->requestStack->getMainRequest();

        // The cookie is scoped to a user id, so anything else authenticating has no topic to name.
        if (!$user instanceof User || !$request instanceof Request) {
            return;
        }

        $this->subscriberCookie->attachTo($event->getResponse(), $user, $request);
    }
}
