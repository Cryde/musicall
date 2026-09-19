<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security\Observability;

use App\Security\Observability\ConsumedRefreshTokenRegistry;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The trail is exercised here rather than through two HTTP requests on purpose: a cache pool is
 * emptied between two KernelBrowser requests, so an API test could never see a token spent by the
 * first request while handling the second. The listener side is covered by unit tests.
 */
class ConsumedRefreshTokenRegistryTest extends KernelTestCase
{
    public function test_a_spent_token_is_found_again_with_how_long_ago_it_went(): void
    {
        self::bootKernel();
        $registry = self::getContainer()->get(ConsumedRefreshTokenRegistry::class);

        $registry->remember('abcdef0123456789', 'fedcba9876543210', 'a-user-id', 'req-1');
        $trail = $registry->lookup('abcdef0123456789');

        $this->assertTrue($trail['previously_consumed']);
        $this->assertSame('fedcba9876543210', $trail['consumed_replacement_hash']);
        $this->assertSame('req-1', $trail['consumed_request_id']);
        $this->assertGreaterThanOrEqual(0, $trail['consumed_ago_ms']);
    }

    /**
     * "Never seen" is a real answer, not a missing one: it means the failure was something other
     * than a replay of a token this server issued.
     */
    public function test_a_token_that_was_never_spent_reads_as_never_seen(): void
    {
        self::bootKernel();
        $registry = self::getContainer()->get(ConsumedRefreshTokenRegistry::class);

        $this->assertSame(['previously_consumed' => false], $registry->lookup('0000000000000000'));
    }

    /**
     * The pool is real here, so this is what catches the key shape. Symfony reserves `{}()/\@:` in a
     * cache key, so the `auth:consumed:` the brief asked for would throw on every write.
     */
    public function test_the_key_shape_is_one_the_cache_accepts(): void
    {
        self::bootKernel();
        $pool = self::getContainer()->get('auth.consumed_tokens');

        $this->assertTrue($pool->getItem('auth_consumed_abcdef0123456789')->isHit() === false);
    }
}
