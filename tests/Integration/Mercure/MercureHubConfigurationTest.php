<?php

declare(strict_types=1);

namespace App\Tests\Integration\Mercure;

use App\Tests\JwtPayload;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\RemoteHubInterface;

/**
 * Guards the two pieces of hub configuration that fail silently.
 *
 * Neither can be caught by anything else here: CI runs no hub, `bin/console about` compiles the
 * container without ever instantiating one, and a wrong value produces a token that is perfectly
 * well formed and simply not allowed to do anything. Both have to be read out of a real container.
 */
class MercureHubConfigurationTest extends KernelTestCase
{
    public function test_the_publisher_token_may_publish_on_any_topic(): void
    {
        self::bootKernel();
        $hub = self::getContainer()->get(HubInterface::class);
        self::assertInstanceOf(RemoteHubInterface::class, $hub);

        // Drop "jwt.publish" from config/packages/mercure.yaml and this claim becomes an empty
        // selector list, which matches no topic, so every publish comes back 401 while the app looks
        // entirely healthy. It is the default when the option is simply left out.
        $this->assertSame(['*'], JwtPayload::of($hub->getProvider()->getJwt())['mercure']['publish']);
    }

    public function test_the_public_url_carries_no_host(): void
    {
        self::bootKernel();
        $hub = self::getContainer()->get(HubInterface::class);
        self::assertInstanceOf(HubInterface::class, $hub);

        // Authorization scopes the subscriber cookie's domain by comparing this host with the host
        // the request came in on, and throws when they are on different second-level domains. The
        // hub is served by the same Caddy as the application, so it is always same-origin and the
        // host is noise: with one it would have to be restated for localhost, musicall.local, the
        // test client's musicall.test and production, and any of them getting it wrong loses
        // realtime for that environment only, quietly.
        $this->assertNull(parse_url($hub->getPublicUrl(), PHP_URL_HOST));
        $this->assertSame('/.well-known/mercure', parse_url($hub->getPublicUrl(), PHP_URL_PATH));
    }
}
