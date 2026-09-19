<?php

declare(strict_types=1);

namespace App\EventSubscriber\Observability;

use App\Enum\Security\AuthEvent;
use App\Security\Observability\AuthContextBuilder;
use App\Security\Observability\AuthFailureReason;
use App\Security\Observability\AuthLogger;
use App\Security\Observability\ConsumedRefreshTokenRegistry;
use Gesdinet\JWTRefreshTokenBundle\Event\RefreshAuthenticationFailureEvent;
use Gesdinet\JWTRefreshTokenBundle\Event\RefreshTokenNotFoundEvent;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\MissingTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTFailureEventInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Logs every way a credential is refused (#1021). The reason is the point: expired, invalid and
 * not-found are three diagnoses, and collapsing them into "401" is what destroys the signal.
 */
#[AsEventListener(event: 'gesdinet.refresh_token_failure', method: 'onRefreshFailure')]
#[AsEventListener(event: 'gesdinet.refresh_token_not_found', method: 'onRefreshTokenNotFound')]
#[AsEventListener(event: Events::JWT_EXPIRED, method: 'onJwtRejected')]
#[AsEventListener(event: Events::JWT_INVALID, method: 'onJwtRejected')]
#[AsEventListener(event: Events::JWT_NOT_FOUND, method: 'onJwtRejected')]
final readonly class AuthFailureObservabilityListener
{
    public function __construct(
        private AuthLogger $authLogger,
        private AuthContextBuilder $contextBuilder,
        private AuthFailureReason $reason,
        private ConsumedRefreshTokenRegistry $registry,
        private RequestStack $requestStack,
        #[Autowire('%gesdinet_jwt_refresh_token.token_parameter_name%')]
        private string $tokenParameterName,
    ) {
    }

    /**
     * Sending nothing and sending something refused arrive on the same event and are not the same
     * diagnosis. The dedicated not-found event only fires from the entry point, which a
     * PUBLIC_ACCESS check path never reaches, so this split is the only way it ever fires.
     */
    public function onRefreshFailure(RefreshAuthenticationFailureEvent $event): void
    {
        // The only guard in this class: everything below reads the trail out of the cache, which is
        // work the switch is meant to stop. AuthLogger guards the logging itself.
        if (!$this->authLogger->isEnabled()) {
            return;
        }

        $exception = $event->getException();

        if ($exception instanceof MissingTokenException) {
            $this->authLogger->log(AuthEvent::RefreshNotFound, [
                'reason' => $this->reason->fromException($exception),
            ]);

            return;
        }

        $this->authLogger->log(AuthEvent::RefreshFailure, [
            'reason' => $this->reason->fromException($exception),
            ...$this->trail(),
        ]);
    }

    public function onRefreshTokenNotFound(RefreshTokenNotFoundEvent $event): void
    {
        $this->authLogger->log(AuthEvent::RefreshNotFound, [
            'reason' => $this->reason->fromException($event->getException()),
        ]);
    }

    public function onJwtRejected(JWTFailureEventInterface $event): void
    {
        if (!$this->carriedACredential()) {
            return;
        }

        $this->authLogger->log(AuthEvent::JwtRejected, [
            'reason' => $this->reason->fromException($event->getException()),
        ]);
    }

    /**
     * Whether this server spent the presented token recently, which is what tells a race apart.
     *
     * @return array<string, mixed>
     */
    private function trail(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof Request) {
            return [];
        }

        $presentedToken = $this->contextBuilder->presentedRefreshToken($request);

        if ($presentedToken === null) {
            return [];
        }

        return $this->registry->lookup($this->contextBuilder->hash($presentedToken));
    }

    /**
     * No credential at all is an anonymous visitor, not a session coming apart: the bulk of the
     * 401s, answering none of the questions here. A half present pair still counts.
     */
    private function carriedACredential(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof Request) {
            return false;
        }

        return $request->headers->has('Authorization')
            || $request->cookies->has('jwt_hp')
            || $request->cookies->has('jwt_s')
            || $request->cookies->has($this->tokenParameterName);
    }
}
