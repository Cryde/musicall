<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mercure;

use App\Entity\User;
use App\Mercure\MercureSubscriberCookie;
use App\Tests\Double\RecordingHub;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubRegistry;

/**
 * The swallowing is the whole design decision, so it gets a test of its own rather than resting on
 * the docblock that argues for it.
 *
 * A hub with no token factory is what a misconfiguration actually looks like from here: it is the
 * state `Authorization::createCookie()` refuses, and `RecordingHub` returns null from `getFactory()`
 * already.
 */
class MercureSubscriberCookieTest extends TestCase
{
    public function test_a_hub_it_cannot_mint_against_leaves_the_response_alone_and_is_logged(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{string, string, array<string, mixed>}> */
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = [(string) $level, (string) $message, $context];
            }
        };

        $user = new User();
        $user->id = 'd1be73fc-b0c4-4530-a30a-d41f43e6ebea';

        $response = new Response();
        (new MercureSubscriberCookie(new Authorization(new HubRegistry(new RecordingHub())), $logger))
            ->attachTo($response, $user, Request::create('https://musicall.test/api/login_check'));

        // Not a cookie, and not an exception either: a login must not fail over this.
        $this->assertSame([], $response->headers->getCookies());

        $this->assertCount(1, $logger->records);
        [$level, , $context] = $logger->records[0];
        $this->assertSame('error', $level);
        $this->assertInstanceOf(\Throwable::class, $context['exception']);
        // The user id is what makes the line actionable: it says whose realtime is silently off.
        $this->assertSame('d1be73fc-b0c4-4530-a30a-d41f43e6ebea', $context['user_id']);
    }
}
