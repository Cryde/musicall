<?php

declare(strict_types=1);

namespace App\Security\Observability;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * A short trail of spent refresh tokens, so a refusal can say whether the token ever existed (#1021).
 * `single_use` deletes the row, so without this a race and an unknown token look identical.
 * Diagnostics only, hence a cache pool: losing it costs the classification and nothing else.
 */
readonly class ConsumedRefreshTokenRegistry
{
    /** Covers a lost response or a laptop waking; later than this honestly reads as "never seen". */
    private const int TTL = 600;

    private const string KEY_PREFIX = 'auth_consumed_';

    public function __construct(
        #[Autowire(service: 'auth.consumed_tokens')]
        private CacheItemPoolInterface $pool,
        #[Target('auth')]
        private LoggerInterface $logger,
    ) {
    }

    public function remember(string $tokenHash, ?string $replacementHash, ?string $userRef, string $requestId): void
    {
        try {
            $item = $this->pool->getItem(self::KEY_PREFIX . $tokenHash);
            $item->set([
                'replacement_hash' => $replacementHash,
                'consumed_at' => microtime(true),
                'user_ref' => $userRef,
                'request_id' => $requestId,
            ]);
            $item->expiresAfter(self::TTL);

            $this->pool->save($item);
        } catch (\Throwable $e) {
            // Instrumentation must never be the reason a refresh fails.
            $this->logger->warning('auth.trail.write_failed', ['exception_type' => $e::class]);
        }
    }

    /**
     * @return array<string, mixed> fields to merge into the failure event
     */
    public function lookup(string $tokenHash): array
    {
        try {
            $item = $this->pool->getItem(self::KEY_PREFIX . $tokenHash);

            if (!$item->isHit()) {
                return ['previously_consumed' => false];
            }

            $entry = $item->get();

            if (!is_array($entry) || !is_float($entry['consumed_at'] ?? null)) {
                return ['previously_consumed' => false];
            }

            return [
                'previously_consumed' => true,
                'consumed_ago_ms' => (int) round((microtime(true) - $entry['consumed_at']) * 1000),
                'consumed_replacement_hash' => $entry['replacement_hash'] ?? null,
                'consumed_request_id' => $entry['request_id'] ?? null,
            ];
        } catch (\Throwable $e) {
            $this->logger->warning('auth.trail.read_failed', ['exception_type' => $e::class]);

            return ['previously_consumed' => null];
        }
    }
}
