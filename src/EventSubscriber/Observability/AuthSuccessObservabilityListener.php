<?php

declare(strict_types=1);

namespace App\EventSubscriber\Observability;

use App\Enum\Security\AuthEvent;
use App\Security\Observability\AuthContextBuilder;
use App\Security\Observability\AuthLogger;
use App\Security\Observability\ConsumedRefreshTokenRegistry;
use Gesdinet\JWTRefreshTokenBundle\Event\RefreshEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Logs the two ways a session is issued, and records the spent token for later classification
 * (#1021). `AuthenticationSuccessEvent` fires for a login and a refresh alike and carries neither
 * token nor request, so `gesdinet.refresh_token` marks the request first; it is dispatched before
 * gesdinet delegates to Lexik, so the mark is always in place.
 */
#[AsEventListener(event: 'gesdinet.refresh_token', method: 'onRefreshTokenConsumed')]
#[AsEventListener(event: Events::AUTHENTICATION_SUCCESS, method: 'onAuthenticationSuccess', priority: self::PRIORITY)]
final readonly class AuthSuccessObservabilityListener
{
    /**
     * After gesdinet's AttachRefreshTokenOnSuccessListener (0) has rotated the token and after
     * NativeCredentialsInBodyListener (-100) has moved it, so the replacement can be read off
     * whichever of the response cookies or the body it ended up in.
     */
    public const int PRIORITY = -200;

    private const string IS_REFRESH_ATTRIBUTE = '_auth_is_refresh';

    public function __construct(
        private AuthLogger $authLogger,
        private AuthContextBuilder $contextBuilder,
        private ConsumedRefreshTokenRegistry $registry,
        private RequestStack $requestStack,
        #[Autowire('%gesdinet_jwt_refresh_token.token_parameter_name%')]
        private string $tokenParameterName,
    ) {
    }

    public function onRefreshTokenConsumed(RefreshEvent $event): void
    {
        $event->getRequest()->attributes->set(self::IS_REFRESH_ATTRIBUTE, true);
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        // Guarded here rather than left to AuthLogger because the refresh branch writes the trail to
        // the cache, which is work the switch is meant to stop.
        if (!$this->authLogger->isEnabled()) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof Request) {
            return;
        }

        if ($request->attributes->get(self::IS_REFRESH_ATTRIBUTE) !== true) {
            $this->authLogger->log(AuthEvent::LoginSuccess, user: $event->getUser());

            return;
        }

        $presentedToken = $this->contextBuilder->presentedRefreshToken($request);
        $replacement = $this->issuedRefreshToken($event);

        $replacementHash = $replacement === null ? null : $this->contextBuilder->hash($replacement);

        $context = $this->authLogger->log(
            AuthEvent::RefreshSuccess,
            ['replacement_hash' => $replacementHash],
            $event->getUser(),
        );

        if ($presentedToken === null) {
            return;
        }

        $this->registry->remember(
            $this->contextBuilder->hash($presentedToken),
            $replacementHash,
            is_string($context['user_ref'] ?? null) ? $context['user_ref'] : null,
            is_string($context['request_id'] ?? null) ? $context['request_id'] : '',
        );
    }

    /**
     * The token the client is being given. Best effort: it lands on a response cookie for the web
     * and in the body for a native client, and it is only ever used to reconstruct a chain.
     */
    private function issuedRefreshToken(AuthenticationSuccessEvent $event): ?string
    {
        foreach ($event->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $this->tokenParameterName) {
                $value = $cookie->getValue();

                return $value === '' ? null : $value;
            }
        }

        $fromBody = $event->getData()[$this->tokenParameterName] ?? null;

        return is_string($fromBody) && $fromBody !== '' ? $fromBody : null;
    }
}
