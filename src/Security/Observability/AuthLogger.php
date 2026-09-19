<?php

declare(strict_types=1);

namespace App\Security\Observability;

use App\Enum\Security\AuthEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The one way an auth event reaches the `auth` channel (#1021).
 *
 * Everything is logged at info, failures included. They are expected business events with a rate,
 * not exceptions, and a single level means the Sentry handler's floor cannot silently drop the
 * successes that form the denominator.
 */
readonly class AuthLogger
{
    public function __construct(
        #[Target('auth')]
        private LoggerInterface $logger,
        private AuthContextBuilder $contextBuilder,
        #[Autowire('%app.auth_observability.enabled%')]
        private bool $enabled,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * The guard lives here rather than in every listener, so a caller only has to check
     * {@see self::isEnabled()} when it does work beyond logging.
     *
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed> what was logged, so a caller needing the same fields again does
     *                              not have to build them a second time
     */
    public function log(AuthEvent $event, array $extra = [], ?UserInterface $user = null): array
    {
        if (!$this->enabled) {
            return [];
        }

        $context = [
            'event' => $event->value,
            'outcome' => $event->outcome(),
            ...$this->contextBuilder->build($user),
            ...$extra,
        ];

        $this->logger->info($event->value, $context);

        return $context;
    }
}
