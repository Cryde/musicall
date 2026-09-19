<?php

declare(strict_types=1);

namespace App\EventSubscriber\Observability;

use App\Enum\Security\AuthEvent;
use App\Security\Observability\AuthLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * The deliberate end of a session, as opposed to the ones being hunted (#1021). Bound to the `api`
 * firewall's dispatcher: LogoutEvent fires there, not globally, so without it nothing would call.
 */
#[AsEventListener(event: LogoutEvent::class, dispatcher: 'security.event_dispatcher.api')]
final readonly class AuthLogoutObservabilityListener
{
    public function __construct(private AuthLogger $authLogger)
    {
    }

    public function __invoke(LogoutEvent $event): void
    {
        $this->authLogger->log(AuthEvent::Logout, user: $event->getToken()?->getUser());
    }
}
